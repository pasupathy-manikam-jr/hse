<?php

use App\Models\Action;
use App\Models\Competency;
use App\Models\Incident;
use App\Models\Observation;
use App\Models\Permit;
use App\Models\RiskAssessment;
use App\Models\Site;
use App\Models\ToolboxTalk;
use App\Models\User;
use App\Models\UserCompetency;
use App\Notifications\DailyDigest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('recording training works out the expiry from the validity and keeps the certificate', function () {
    Storage::fake('local');
    $competency = Competency::create(['name' => 'Confined space entry', 'validity_months' => 24, 'permit_type' => 'confined-space']);
    $person = User::factory()->create();
    $this->actingAs($this->userWithRole('supervisor'));

    $this->post(route('competencies.records.store'), [
        'user_id' => $person->id, 'competency_id' => $competency->id, 'issued_on' => '2026-01-10',
        'file' => UploadedFile::fake()->create('ticket.pdf', 50, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $record = UserCompetency::query()->sole();
    expect($record->expires_on->toDateString())->toBe('2028-01-10')
        ->and($record->file_sha256)->toHaveLength(64);
    $this->get(route('competencies.records.file', $record))->assertOk();
});

test('the matrix shows each person\'s latest record per competency', function () {
    $competency = Competency::create(['name' => 'First aid', 'validity_months' => 36]);
    $person = User::factory()->create(['name' => 'Ali']);
    UserCompetency::create(['user_id' => $person->id, 'competency_id' => $competency->id, 'issued_on' => '2020-01-01', 'expires_on' => '2023-01-01']);
    UserCompetency::create(['user_id' => $person->id, 'competency_id' => $competency->id, 'issued_on' => '2025-01-01', 'expires_on' => '2028-01-01']);

    $this->actingAs($this->userWithRole('auditor'))->get(route('competencies.index', ['search' => 'Ali']))
        ->assertInertia(fn (Assert $page) => $page->component('competencies/index')
            ->has('people', 1)
            ->has('people.0.records', 1)
            ->where('people.0.records.0.expires_on', '2028-01-01'));
});

test('competency names are unique and permit types are validated', function () {
    Competency::create(['name' => 'Hot work']);
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->post(route('competencies.store'), ['name' => 'Hot work', 'permit_type' => 'welding'])->assertSessionHasErrors(['name', 'permit_type']);
    $this->actingAs($this->userWithRole('auditor'))->post(route('competencies.store'), ['name' => 'X'])->assertForbidden();
});

test('a toolbox talk records attendees and can only cite an incident at its site', function () {
    $site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $elsewhere = Incident::create(['site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id, 'type' => 'injury', 'title' => 'x', 'description' => 'x', 'occurred_at' => now()]);
    $people = User::factory()->count(3)->create();
    $this->actingAs($this->userWithRole('supervisor'));

    $data = ['site_id' => $site->id, 'topic' => 'Gin wheels', 'held_on' => today()->toDateString(), 'presenter_id' => $people[0]->id, 'attendee_ids' => $people->pluck('id')->all()];
    $this->post(route('toolbox-talks.store'), [...$data, 'incident_id' => $elsewhere->id])->assertSessionHasErrors('incident_id');
    $this->post(route('toolbox-talks.store'), $data)->assertSessionHasNoErrors();

    expect(ToolboxTalk::query()->sole())->number->toBe('TBT-'.now()->year.'-0001')
        ->and(ToolboxTalk::query()->sole()->attendees)->toHaveCount(3);
});

test('the daily reminders email each person what needs them', function () {
    Notification::fake();
    $site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $owner = User::factory()->create();
    $holder = User::factory()->create();
    $requester = User::factory()->create();
    $nobody = User::factory()->create();

    $observation = Observation::create(['site_id' => $site->id, 'type' => 'unsafe-act', 'potential' => 'low', 'description' => 'x', 'observed_at' => now()]);
    $observation->raiseAction(['description' => 'Overdue fix', 'control_level' => 'ppe', 'priority' => 'low', 'owner_id' => $owner->id, 'due_on' => today()->subDay()]);
    $observation->raiseAction(['description' => 'Later fix', 'control_level' => 'ppe', 'priority' => 'low', 'owner_id' => $nobody->id, 'due_on' => today()->addWeek()]);

    $competency = Competency::create(['name' => 'First aid', 'validity_months' => 36]);
    UserCompetency::create(['user_id' => $holder->id, 'competency_id' => $competency->id, 'issued_on' => today()->subYears(3), 'expires_on' => today()->addDays(10)]);
    // Renewed already: the old record must not trigger a reminder.
    UserCompetency::create(['user_id' => $nobody->id, 'competency_id' => $competency->id, 'issued_on' => today()->subYears(3), 'expires_on' => today()->addDays(5)]);
    UserCompetency::create(['user_id' => $nobody->id, 'competency_id' => $competency->id, 'issued_on' => today(), 'expires_on' => today()->addYears(3)]);

    $ra = RiskAssessment::create(['site_id' => $site->id, 'type' => 'jsa', 'title' => 'x', 'activity' => 'x', 'review_due_on' => today()->addYear()]);
    $permit = Permit::create(['type' => 'general', 'site_id' => $site->id, 'risk_assessment_id' => $ra->id, 'description' => 'x', 'valid_from' => now()->subHours(9), 'valid_to' => now()->subHour()]);
    $permit->forceFill(['status' => 'active', 'created_by' => $requester->id])->save();

    $this->artisan('hse:reminders')->assertSuccessful();

    Notification::assertSentTo($owner, DailyDigest::class, fn (DailyDigest $n) => str_contains(implode(' ', $n->sections['Actions due']), 'Overdue fix'));
    Notification::assertSentTo($holder, DailyDigest::class, fn (DailyDigest $n) => isset($n->sections['Competencies expiring']));
    Notification::assertSentTo($requester, DailyDigest::class, fn (DailyDigest $n) => isset($n->sections['Permits to close']));
    Notification::assertNotSentTo($nobody, DailyDigest::class);
    expect(Action::query()->count())->toBe(2);
});
