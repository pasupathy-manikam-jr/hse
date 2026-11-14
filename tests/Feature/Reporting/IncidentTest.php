<?php

use App\Models\Incident;
use App\Models\Observation;
use App\Models\Site;
use App\Models\SiteMetric;
use App\Models\User;
use App\Notifications\DailyDigest;
use App\Support\Jkkp8Register;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->area = $this->site->areas()->create(['name' => 'Block A']);
});

function incidentData(array $overrides = []): array
{
    return [
        'site_id' => test()->site->id, 'area_id' => test()->area->id, 'type' => 'injury', 'title' => 'Hand crushed',
        'description' => 'Hand caught between tubes.', 'occurred_at' => now()->subHour()->toIso8601String(), ...$overrides,
    ];
}

function makeIncident(array $overrides = []): Incident
{
    return Incident::create(incidentData($overrides));
}

function injure(Incident $incident, string $treatment, array $overrides = []): void
{
    $incident->people()->create(['name' => 'Ali', 'role' => 'injured', 'treatment' => $treatment, ...$overrides]);
}

function investigate(Incident $incident): void
{
    $incident->update(['whys' => ['Passed by hand.'], 'root_cause_category' => 'equipment', 'root_cause' => 'Too few gin wheels.']);
}

test('a supervisor reports an incident with photos and lands on its page', function () {
    Storage::fake('local');
    $this->actingAs($supervisor = $this->userWithRole('supervisor'));

    $this->get(route('incidents.create'))->assertInertia(fn (Assert $page) => $page->component('incidents/create'));
    $response = $this->post(route('incidents.store'), incidentData(['photos' => [UploadedFile::fake()->image('a.jpg')]]));

    $incident = Incident::query()->sole();
    $response->assertRedirect(route('incidents.show', $incident));
    expect($incident->number)->toBe('INC-'.now()->year.'-0001')
        ->and($incident->status)->toBe('reported')
        ->and($incident->classification)->toBe('no-injury')
        ->and($incident->created_by)->toBe($supervisor->id)
        ->and($incident->photos)->toHaveCount(1);
});

test('an observation is escalated once, with its details pre-filled', function () {
    $observation = Observation::create(['site_id' => $this->site->id, 'type' => 'near-miss', 'potential' => 'high', 'description' => 'Load slipped.', 'observed_at' => now()->subDay()]);
    $this->actingAs($this->userWithRole('supervisor'));

    $this->get(route('incidents.create', ['observation' => $observation->id]))
        ->assertInertia(fn (Assert $page) => $page->where('observation.number', $observation->number)->where('observation.description', 'Load slipped.'));

    $this->post(route('incidents.store'), incidentData(['observation_id' => $observation->id]))->assertSessionHasNoErrors();
    $this->post(route('incidents.store'), incidentData(['observation_id' => $observation->id]))->assertSessionHasErrors('observation_id');

    expect($observation->incident->observation_id)->toBe($observation->id);
    $this->get(route('incidents.create', ['observation' => $observation->id]))->assertInertia(fn (Assert $page) => $page->where('observation', null));
});

test('the classification follows the worst treatment among the injured, and decides recordability', function () {
    $incident = makeIncident();

    injure($incident, 'first-aid');
    expect($incident->fresh())->classification->toBe('first-aid')->recordable->toBeFalse();

    $incident->people()->create(['name' => 'Witness', 'role' => 'witness']);
    injure($incident, 'medical');
    expect($incident->fresh())->classification->toBe('medical')->recordable->toBeTrue();

    $incident->people()->where('treatment', 'medical')->sole()->delete();
    expect($incident->fresh())->classification->toBe('first-aid')->recordable->toBeFalse();
});

test('people are validated: treatment only for the injured, days lost for lost time, a name or a user', function () {
    $incident = makeIncident();
    $staff = User::factory()->create(['name' => 'Siti Aminah']);
    $this->actingAs($this->userWithRole('supervisor'));

    $this->post(route('incidents.people.store', $incident), ['role' => 'witness', 'name' => 'Ali', 'treatment' => 'medical'])->assertSessionHasErrors('treatment');
    $this->post(route('incidents.people.store', $incident), ['role' => 'injured', 'name' => 'Ali'])->assertSessionHasErrors('treatment');
    $this->post(route('incidents.people.store', $incident), ['role' => 'injured', 'name' => 'Ali', 'treatment' => 'lost-time'])->assertSessionHasErrors('days_lost');
    $this->post(route('incidents.people.store', $incident), ['role' => 'witness'])->assertSessionHasErrors('name');

    $this->post(route('incidents.people.store', $incident), ['role' => 'injured', 'user_id' => $staff->id, 'treatment' => 'lost-time', 'days_lost' => 4])->assertSessionHasNoErrors();

    $person = $incident->people()->sole();
    expect($person->name)->toBe('Siti Aminah')->and($person->days_lost)->toBe(4)
        ->and($incident->fresh()->classification)->toBe('lost-time');
});

test('an illness needs its OSHA type; injuries must not have one', function () {
    $illness = makeIncident(['type' => 'illness']);
    $injury = makeIncident();
    $this->actingAs($this->userWithRole('supervisor'));

    $this->post(route('incidents.people.store', $illness), ['role' => 'injured', 'name' => 'Ali', 'treatment' => 'medical'])->assertSessionHasErrors('illness_type');
    $this->post(route('incidents.people.store', $injury), ['role' => 'injured', 'name' => 'Ali', 'treatment' => 'medical', 'illness_type' => 'poisoning'])->assertSessionHasErrors('illness_type');
    $this->post(route('incidents.people.store', $illness), ['role' => 'injured', 'name' => 'Ali', 'treatment' => 'medical', 'illness_type' => 'respiratory', 'privacy_case' => true])->assertSessionHasNoErrors();

    expect($illness->people()->sole())->illness_type->toBe('respiratory')->privacy_case->toBeTrue();
});

test('saving the investigation starts it and drops empty whys', function () {
    $incident = makeIncident();

    $this->actingAs($this->userWithRole('supervisor'))->put(route('incidents.investigate', $incident), [
        'whys' => ['Passed by hand.', '', 'Gin wheel in use elsewhere.', null, ''], 'root_cause_category' => 'equipment', 'root_cause' => 'Too few gin wheels.',
    ])->assertSessionHasNoErrors();

    expect($incident->fresh())->status->toBe('under-investigation')
        ->whys->toBe(['Passed by hand.', 'Gin wheel in use elsewhere.']);
    expect($incident->fresh()->investigationComplete())->toBeTrue();
});

test('moving to actions in progress needs a complete investigation and an action', function () {
    $incident = makeIncident();
    $incident->transitionTo('under-investigation');
    $this->actingAs($this->userWithRole('supervisor'));

    $this->put(route('incidents.transition', $incident), ['status' => 'actions-in-progress'])->assertSessionHasErrors('status');
    investigate($incident);
    $this->put(route('incidents.transition', $incident), ['status' => 'actions-in-progress'])->assertSessionHasErrors('status');

    $incident->raiseAction(['description' => 'Buy gin wheels.', 'control_level' => 'engineering', 'priority' => 'high', 'owner_id' => User::factory()->create()->id, 'due_on' => today()]);
    $this->put(route('incidents.transition', $incident), ['status' => 'actions-in-progress'])->assertSessionHasNoErrors();

    expect($incident->fresh()->status)->toBe('actions-in-progress');
});

test('an incident closes only when its actions are verified, and a recordable one only once investigated', function () {
    $incident = makeIncident();
    injure($incident, 'medical');
    $owner = User::factory()->create();
    $action = $incident->raiseAction(['description' => 'Buy gin wheels.', 'control_level' => 'engineering', 'priority' => 'high', 'owner_id' => $owner->id, 'due_on' => today()]);
    $this->actingAs($supervisor = $this->userWithRole('supervisor'));

    $this->put(route('incidents.transition', $incident), ['status' => 'closed'])->assertSessionHasErrors('status');
    investigate($incident);
    $this->put(route('incidents.transition', $incident), ['status' => 'closed'])->assertSessionHasErrors('status');

    $action->transitionTo('done', $owner, 'Bought.');
    $action->transitionTo('verified', $supervisor);
    $this->put(route('incidents.transition', $incident), ['status' => 'closed'])->assertSessionHasNoErrors();

    expect($incident->fresh())->status->toBe('closed')->closed_by->toBe($supervisor->id);
    $this->post(route('incidents.people.store', $incident), ['role' => 'witness', 'name' => 'Late'])->assertSessionHasErrors('status');
});

test('a minor incident with no actions closes straight from reported', function () {
    $incident = makeIncident();
    injure($incident, 'first-aid');

    $this->actingAs($this->userWithRole('supervisor'))->put(route('incidents.transition', $incident), ['status' => 'closed'])->assertSessionHasNoErrors();

    expect($incident->fresh()->status)->toBe('closed');
});

test('incidents are scoped to the user\'s site and closed to workers', function () {
    $other = makeIncident(['site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id, 'area_id' => null]);
    makeIncident();
    $supervisor = $this->userWithRole('supervisor');
    $supervisor->update(['site_id' => $this->site->id]);

    $this->actingAs($supervisor)->get(route('incidents.index'))
        ->assertInertia(fn (Assert $page) => $page->component('incidents/index')->has('incidents.data', 1)->where('counts.reported', 1));
    $this->get(route('incidents.show', $other))->assertNotFound();
    $this->put(route('incidents.transition', $other), ['status' => 'closed'])->assertNotFound();

    $this->actingAs($this->userWithRole('worker'))->get(route('incidents.index'))->assertForbidden();
    $this->actingAs($this->userWithRole('auditor'))->put(route('incidents.transition', $other), ['status' => 'closed'])->assertForbidden();
});

test('the action register links back to the incident', function () {
    $incident = makeIncident();
    $incident->raiseAction(['description' => 'x', 'control_level' => 'ppe', 'priority' => 'low', 'owner_id' => User::factory()->create()->id, 'due_on' => today()]);

    $this->actingAs($this->userWithRole('hse-manager'))->get(route('actions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('actions.data.0.source_ref.number', $incident->number)
            ->where('actions.data.0.source_ref.url', route('incidents.show', $incident)));
});

test('the injury log lists recordable cases for the site and year, with OSHA totals', function () {
    $lastYear = makeIncident(['occurred_at' => now()->subYear()]);
    injure($lastYear, 'lost-time', ['days_lost' => 5]);
    $crush = makeIncident();
    injure($crush, 'lost-time', ['days_lost' => 200, 'days_restricted' => 20]);
    injure($crush, 'first-aid');
    $illness = makeIncident(['type' => 'illness']);
    injure($illness, 'restricted', ['days_restricted' => 7, 'illness_type' => 'hearing-loss', 'privacy_case' => true]);
    $elsewhere = makeIncident(['site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id, 'area_id' => null]);
    injure($elsewhere, 'fatality');

    $this->actingAs($this->userWithRole('hse-manager'))->get(route('incidents.log', ['site_id' => $this->site->id, 'year' => now()->year]))
        ->assertInertia(fn (Assert $page) => $page->component('incidents/log')
            ->has('cases', 2)
            ->where('totals', [
                'deaths' => 0, 'days_away_cases' => 1, 'restricted_cases' => 1, 'other_cases' => 0,
                'days_away' => 180, 'days_restricted' => 27, 'injuries' => 1,
                'skin-disorder' => 0, 'respiratory' => 0, 'poisoning' => 0, 'hearing-loss' => 1, 'other-illness' => 0,
            ])
            ->where('cases.1.name', 'Privacy case')
            ->where('cases.1.category', 'hearing-loss'));
});

test('an incident with actions cannot be deleted', function () {
    $incident = makeIncident();
    $incident->raiseAction(['description' => 'x', 'control_level' => 'ppe', 'priority' => 'low', 'owner_id' => User::factory()->create()->id, 'due_on' => today()]);
    $plain = makeIncident();
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->delete(route('incidents.destroy', $incident));
    $this->delete(route('incidents.destroy', $plain));

    expect($incident->fresh())->not->toBeNull()->and($plain->fresh())->toBeNull();
});

test('NADOPOD: a death, serious injury or dangerous occurrence needs DOSH told at once and JKKP 6; over 4 days off needs JKKP 6; a disease needs JKKP 7', function () {
    $minor = makeIncident();
    injure($minor, 'medical', ['days_lost' => 4]);
    $offWork = makeIncident(['occurred_at' => '2026-11-02 10:00']);
    injure($offWork, 'lost-time', ['days_lost' => 5]);
    $fracture = makeIncident();
    injure($fracture, 'medical', ['serious_injury' => true]);
    $collapse = makeIncident(['type' => 'property-damage', 'dangerous_occurrence' => '110']);
    $disease = makeIncident(['type' => 'illness']);
    injure($disease, 'medical', ['illness_type' => 'hearing-loss']);

    expect($minor->load('people')->doshDuty())->toBeNull()
        ->and($offWork->load('people')->doshDuty())->toMatchArray(['form' => 'JKKP 6', 'immediate' => false, 'due_on' => '2026-11-09'])
        ->and($fracture->load('people')->doshDuty())->toMatchArray(['form' => 'JKKP 6', 'immediate' => true])
        ->and($collapse->load('people')->doshDuty())->toMatchArray(['form' => 'JKKP 6', 'immediate' => true, 'reasons' => ['Dangerous occurrence 110']])
        ->and($disease->load('people')->doshDuty())->toMatchArray(['form' => 'JKKP 7', 'immediate' => false]);
});

test('the DOSH notice and JKKP form are recorded on the incident, and it cannot close before the form is sent', function () {
    $incident = makeIncident(['dangerous_occurrence' => '610', 'type' => 'property-damage']);
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->put(route('incidents.transition', $incident), ['status' => 'closed'])->assertSessionHasErrors('status');
    $this->put(route('incidents.dosh-notice', $incident), ['notified_at' => now()->toIso8601String()])->assertSessionHasErrors('detail');
    $this->put(route('incidents.dosh-notice', $incident), ['notified_at' => now()->toIso8601String(), 'detail' => 'Phoned JKKP Selangor'])->assertSessionHasNoErrors();
    $this->put(route('incidents.jkkp', $incident), ['submitted_at' => now()->addDay()->toIso8601String(), 'reference' => 'X'])->assertSessionHasErrors('submitted_at');
    $this->put(route('incidents.jkkp', $incident), ['submitted_at' => now()->toIso8601String(), 'reference' => 'JKKP6-2026-0412'])->assertSessionHasNoErrors();

    expect($incident->fresh()->load('people')->doshDuty())->toMatchArray(['notified' => true, 'submitted' => true]);
    $this->get(route('incidents.show', $incident))->assertInertia(fn (Assert $page) => $page->where('dosh.form', 'JKKP 6')->where('incident.jkkp_reference', 'JKKP6-2026-0412'));
    $this->put(route('incidents.transition', $incident), ['status' => 'closed'])->assertSessionHasNoErrors();

    $this->put(route('incidents.jkkp', makeIncident()), ['submitted_at' => now()->toIso8601String(), 'reference' => 'X'])->assertNotFound();
    $this->actingAs($this->userWithRole('auditor'))->put(route('incidents.dosh-notice', makeIncident()), ['notified_at' => now()->toIso8601String(), 'detail' => 'x'])->assertForbidden();
});

test('the JKKP 8 register lists the year\'s cases and works out the rates from hours and workers', function () {
    $year = now()->year;
    $death = makeIncident(['occurred_at' => "{$year}-01-10 09:00"]);
    injure($death, 'fatality');
    $hand = makeIncident(['occurred_at' => "{$year}-02-10 09:00"]);
    injure($hand, 'lost-time', ['days_lost' => 10, 'privacy_case' => true]);
    injure(makeIncident(['occurred_at' => "{$year}-02-11 09:00"]), 'first-aid');
    $noise = makeIncident(['type' => 'illness', 'occurred_at' => "{$year}-03-01 09:00"]);
    injure($noise, 'medical', ['illness_type' => 'hearing-loss']);
    makeIncident(['type' => 'property-damage', 'dangerous_occurrence' => '110', 'occurred_at' => "{$year}-04-01 09:00"]);
    foreach (['01', '02'] as $m) {
        SiteMetric::create(['site_id' => $this->site->id, 'month' => "{$year}-{$m}-01", 'metric' => SiteMetric::HOURS, 'value' => 250_000]);
        SiteMetric::create(['site_id' => $this->site->id, 'month' => "{$year}-{$m}-01", 'metric' => SiteMetric::WORKERS, 'value' => 1_000]);
    }

    $register = (new Jkkp8Register($this->site->id, $year))->data();

    expect($register['accidents'])->toHaveCount(2)
        ->and($register['accidents'][1]['name'])->toBe('Privacy case')
        ->and($register['diseases'])->toHaveCount(1)
        ->and($register['occurrences'])->toHaveCount(1)
        ->and($register['summary'])->toMatchArray([
            'accident_cases' => 2, 'accident_deaths' => 1, 'accident_days_lost' => 6010, 'man_hours' => 500000.0, 'average_workers' => 1000.0,
            'incident_rate' => 2.0, 'fatality_rate' => 1.0, 'frequency_rate' => 4.0, 'severity_rate' => 12020.0, 'due_on' => ($year + 1).'-01-31',
        ]);

    $this->actingAs($this->userWithRole('auditor'))->get(route('incidents.register', ['site_id' => $this->site->id, 'year' => $year]))
        ->assertInertia(fn (Assert $page) => $page->component('incidents/register')->where('summary.accident_cases', 2));
});

test('people who edit incidents at the site are reminded of a JKKP form still owed', function () {
    Notification::fake();
    $supervisor = $this->userWithRole('supervisor');
    $supervisor->update(['site_id' => $this->site->id]);
    $elsewhere = $this->userWithRole('supervisor');
    $elsewhere->update(['site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id]);
    $incident = makeIncident();
    injure($incident, 'lost-time', ['days_lost' => 6]);

    $this->artisan('hse:reminders')->assertSuccessful();

    Notification::assertSentTo($supervisor, DailyDigest::class, fn (DailyDigest $n) => str_contains($n->sections['Report to DOSH'][0], 'JKKP 6'));
    Notification::assertNotSentTo($elsewhere, DailyDigest::class);
});
