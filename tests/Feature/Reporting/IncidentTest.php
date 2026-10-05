<?php

use App\Models\Incident;
use App\Models\Observation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
