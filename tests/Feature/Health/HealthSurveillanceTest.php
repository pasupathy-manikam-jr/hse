<?php

use App\Models\HealthExam;
use App\Models\HealthProgramme;
use App\Models\Site;
use App\Models\User;
use App\Notifications\DailyDigest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
});

function noiseProgramme(array $overrides = []): HealthProgramme
{
    return HealthProgramme::create(['site_id' => test()->site->id, 'name' => 'Noise: breakers', 'hazard' => 'noise', 'exam_interval_months' => 12,
        'monitoring_interval_months' => 60, 'exposure_limit' => 85, 'unit' => 'dB(A)', ...$overrides]);
}

function examine(HealthProgramme $programme, User $worker, string $outcome, string $type = 'periodic', ?string $on = null): HealthExam
{
    $exam = new HealthExam(['user_id' => $worker->id, 'type' => $type, 'examined_on' => $on ?? today()->toDateString(), 'provider' => 'ATC Klang', 'outcome' => $outcome]);
    $exam->programme()->associate($programme)->save();

    return $exam;
}

test('noise: baseline within 3 months of starting, then yearly; a temporary shift is retested within 3 months', function () {
    $programme = noiseProgramme();
    $late = User::factory()->create(['name' => 'Ali']);
    $new = User::factory()->create(['name' => 'Bala']);
    $programme->members()->create(['user_id' => $late->id, 'started_on' => today()->subMonths(4)]);
    $programme->members()->create(['user_id' => $new->id, 'started_on' => today()->subWeek()]);

    $rows = collect($programme->schedule())->keyBy('name');
    expect($rows['Ali'])->toMatchArray(['next_type' => 'baseline', 'due_on' => today()->subMonth()->toDateString(), 'state' => 'overdue'])
        ->and($rows['Bala'])->toMatchArray(['next_type' => 'baseline', 'state' => 'ok']);

    examine($programme, $late, 'normal', 'baseline');
    expect(collect($programme->schedule())->keyBy('name')['Ali'])->toMatchArray(['next_type' => 'periodic', 'due_on' => today()->addYear()->toDateString()]);

    examine($programme, $late, 'temporary-sts', 'periodic', today()->addDay()->toDateString());
    expect(collect($programme->schedule())->keyBy('name')['Ali'])->toMatchArray(['next_type' => 'retest', 'due_on' => today()->addDay()->addMonths(3)->toDateString()]);
});

test('a permanent shift, hearing impairment or NIHL is notifiable, except when found at a baseline within 3 months of starting', function () {
    $programme = noiseProgramme();
    $worker = User::factory()->create();
    $programme->members()->create(['user_id' => $worker->id, 'started_on' => today()->subMonth()]);

    expect(examine($programme, $worker, 'hearing-impairment', 'baseline')->notify_dosh)->toBeFalse()
        ->and(examine($programme, $worker, 'permanent-sts')->notify_dosh)->toBeTrue()
        ->and(examine($programme, $worker, 'normal')->notify_dosh)->toBeFalse();

    $late = User::factory()->create();
    $programme->members()->create(['user_id' => $late->id, 'started_on' => today()->subMonths(6)]);
    expect(examine($programme, $late, 'nihl', 'baseline')->notify_dosh)->toBeTrue();
});

test('chemicals: an unfit worker is removed from the exposure, and a Schedule II programme cannot be examined less than yearly', function () {
    $this->actingAs($this->userWithRole('hse-manager'));
    $this->post(route('health.store'), ['site_id' => $this->site->id, 'name' => 'Silica', 'hazard' => 'chemical', 'schedule_ii' => true, 'exam_interval_months' => 24, 'monitoring_interval_months' => 12])
        ->assertSessionHasErrors(['exam_interval_months', 'monitoring_interval_months']);
    $this->post(route('health.store'), ['site_id' => $this->site->id, 'name' => 'Silica', 'hazard' => 'chemical', 'schedule_ii' => true, 'exam_interval_months' => 12, 'monitoring_interval_months' => 6])
        ->assertRedirect();

    $programme = HealthProgramme::query()->sole();
    $worker = User::factory()->create(['site_id' => $this->site->id]);
    $this->post(route('health.members.store', $programme), ['user_id' => $worker->id, 'started_on' => today()->subYear()->toDateString()])->assertSessionHasNoErrors();
    $this->post(route('health.exams.store', $programme), ['user_id' => $worker->id, 'type' => 'periodic', 'examined_on' => today()->toDateString(), 'provider' => 'Dr Tan, OHD', 'outcome' => 'normal'])
        ->assertSessionHasErrors('outcome');
    $this->post(route('health.exams.store', $programme), ['user_id' => User::factory()->create()->id, 'type' => 'periodic', 'examined_on' => today()->toDateString(), 'provider' => 'x', 'outcome' => 'fit'])
        ->assertSessionHasErrors('user_id');
    $this->post(route('health.exams.store', $programme), ['user_id' => $worker->id, 'type' => 'periodic', 'examined_on' => today()->toDateString(), 'provider' => 'Dr Tan, OHD',
        'outcome' => 'unfit', 'notes' => 'Reduced lung function.', 'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();

    expect($programme->schedule()[0])->toMatchArray(['state' => 'removed', 'due_on' => null]);
});

test('the worker is told within 21 days and DOSH notified, both recorded; readings set the next monitoring date', function () {
    $this->actingAs($this->userWithRole('hse-manager'));
    $programme = noiseProgramme();
    $worker = User::factory()->create();
    $programme->members()->create(['user_id' => $worker->id, 'started_on' => today()->subYear()]);
    $exam = examine($programme, $worker, 'permanent-sts');
    $normal = examine($programme, $worker, 'normal');

    $this->put(route('health.exams.notified', [$programme, $normal]), ['dosh_notified_on' => today()->toDateString(), 'dosh_reference' => 'x'])->assertSessionHasErrors('status');
    $this->put(route('health.exams.notified', [$programme, $exam]), ['dosh_notified_on' => today()->toDateString()])->assertSessionHasErrors('dosh_reference');
    $this->put(route('health.exams.notified', [$programme, $exam]), ['dosh_notified_on' => today()->toDateString(), 'dosh_reference' => 'ONRHD/2026/12'])->assertSessionHasNoErrors();
    $this->put(route('health.exams.informed', [$programme, $exam]), ['informed_on' => today()->toDateString()])->assertSessionHasNoErrors();
    expect($exam->fresh())->dosh_reference->toBe('ONRHD/2026/12')->informed_on->not->toBeNull();

    $this->post(route('health.readings.store', $programme), ['measured_on' => today()->toDateString(), 'value' => 91.5, 'assessor' => 'NRA Lim'])->assertSessionHasNoErrors();
    expect($programme->readings()->sole()->value)->toBe('91.500')
        ->and($programme->monitoringDueOn()?->toDateString())->toBe(today()->addMonths(60)->toDateString());
});

test('medical results are for HSE managers only; each worker sees just their own', function () {
    $programme = noiseProgramme();
    $worker = $this->userWithRole('worker');
    $other = $this->userWithRole('worker');
    $programme->members()->create(['user_id' => $worker->id, 'started_on' => today()->subYear()]);
    $exam = examine($programme, $worker, 'normal');
    $exam->attachUpload(UploadedFile::fake()->create('audiogram.pdf', 10, 'application/pdf'))->save();

    $this->actingAs($this->userWithRole('supervisor'))->get(route('health.index'))->assertForbidden();
    $this->actingAs($this->userWithRole('auditor'))->get(route('health.show', $programme))->assertForbidden();

    $this->actingAs($worker)->get(route('health.mine'))->assertInertia(fn (Assert $page) => $page->component('health/mine')->has('exams', 1)->has('memberships', 1));
    $this->get(route('health.exams.file', $exam))->assertOk();
    $this->actingAs($other)->get(route('health.exams.file', $exam))->assertNotFound();
    $this->get(route('health.mine'))->assertInertia(fn (Assert $page) => $page->has('exams', 0));
});

test('HSE managers are reminded of examinations due and results to notify', function () {
    Notification::fake();
    $manager = $this->userWithRole('hse-manager');
    $programme = noiseProgramme();
    $worker = User::factory()->create(['name' => 'Ali']);
    $programme->members()->create(['user_id' => $worker->id, 'started_on' => today()->subMonths(4)]);

    $this->artisan('hse:reminders')->assertSuccessful();

    Notification::assertSentTo($manager, DailyDigest::class, fn (DailyDigest $n) => str_contains(implode(' ', $n->sections['Health surveillance']), 'Ali, baseline due'));
});
