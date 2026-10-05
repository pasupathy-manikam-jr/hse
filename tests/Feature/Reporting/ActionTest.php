<?php

use App\Models\Action;
use App\Models\Observation;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->observation = Observation::create([
        'site_id' => $this->site->id, 'type' => 'near-miss', 'potential' => 'high', 'description' => 'Load slipped.', 'observed_at' => now(),
    ]);
});

function raiseTestAction(User $owner, array $overrides = []): Action
{
    return test()->observation->raiseAction([
        'description' => 'Barricade the swing radius.', 'control_level' => 'engineering', 'priority' => 'high',
        'owner_id' => $owner->id, 'due_on' => today()->addWeek(), ...$overrides,
    ]);
}

test('the owner marks an action done with notes, and only the owner can', function () {
    $owner = $this->userWithRole('worker');
    $action = raiseTestAction($owner);

    $this->actingAs($this->userWithRole('supervisor'))->put(route('actions.complete', $action), ['notes' => 'Done.'])
        ->assertSessionHasErrors('notes');
    $this->actingAs($owner)->put(route('actions.complete', $action), ['notes' => ''])->assertSessionHasErrors('notes');
    $this->actingAs($owner)->put(route('actions.complete', $action), ['notes' => 'Barriers installed.'])->assertSessionHasNoErrors();

    expect($action->fresh())->status->toBe('done')->completion_notes->toBe('Barriers installed.')->done_at->not->toBeNull();
});

test('an action cannot be verified by its own owner, even one who holds verify-actions', function () {
    $owner = $this->userWithRole('supervisor');
    $action = raiseTestAction($owner);
    $action->transitionTo('done', $owner, 'Barriers installed.');

    $this->actingAs($owner)->put(route('actions.verify', $action))->assertSessionHasErrors('notes');
    expect($action->fresh()->status)->toBe('done');

    $this->actingAs($verifier = $this->userWithRole('hse-manager'))->put(route('actions.verify', $action))->assertSessionHasNoErrors();
    expect($action->fresh())->status->toBe('verified')->verified_by->toBe($verifier->id);
});

test('a verifier sends an action back with a reason, and it reopens for the owner', function () {
    $owner = $this->userWithRole('worker');
    $action = raiseTestAction($owner);
    $action->transitionTo('done', $owner, 'Done.');
    $this->actingAs($this->userWithRole('supervisor'));

    $this->put(route('actions.reject', $action), ['notes' => ''])->assertSessionHasErrors('notes');
    $this->put(route('actions.reject', $action), ['notes' => 'Barrier does not cover the east side.'])->assertSessionHasNoErrors();

    expect($action->fresh())->status->toBe('open')->rejection_reason->toBe('Barrier does not cover the east side.')->done_at->toBeNull();
});

test('steps cannot be skipped', function () {
    $owner = $this->userWithRole('worker');
    $action = raiseTestAction($owner);

    $this->actingAs($this->userWithRole('supervisor'))->put(route('actions.verify', $action))->assertSessionHasErrors('status');
});

test('workers need verify-actions to verify', function () {
    $owner = User::factory()->create();
    $action = raiseTestAction($owner);
    $action->transitionTo('done', $owner, 'Done.');

    $this->actingAs($this->userWithRole('worker'))->put(route('actions.verify', $action))->assertForbidden();
});

test('without manage-actions a user sees only their own actions', function () {
    $worker = $this->userWithRole('worker');
    raiseTestAction($worker);
    raiseTestAction(User::factory()->create());

    $this->actingAs($worker)->get(route('actions.index'))
        ->assertInertia(fn (Assert $page) => $page->component('actions/index')
            ->has('actions.data', 1)
            ->where('actions.data.0.owner_id', $worker->id)
            ->where('actions.data.0.source_ref.number', $this->observation->number)
            ->where('actions.data.0.source_ref.url', null));
});

test('managers see the register for their site, filter overdue, and get a link to the source', function () {
    $owner = User::factory()->create();
    raiseTestAction($owner);
    raiseTestAction($owner, ['due_on' => today()->subDay()]);
    $elsewhere = Observation::create(['site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id, 'type' => 'positive', 'potential' => 'low', 'description' => 'x', 'observed_at' => now()]);
    $elsewhere->raiseAction(['description' => 'x', 'control_level' => 'ppe', 'priority' => 'low', 'owner_id' => $owner->id, 'due_on' => today()]);
    $supervisor = $this->userWithRole('supervisor');
    $supervisor->update(['site_id' => $this->site->id]);

    $this->actingAs($supervisor)->get(route('actions.index'))
        ->assertInertia(fn (Assert $page) => $page->has('actions.data', 2)
            ->where('counts.all', 2)
            ->where('actions.data.0.source_ref.url', route('observations.show', $this->observation)));

    $this->get(route('actions.index', ['owner' => 'overdue']))->assertInertia(fn (Assert $page) => $page->has('actions.data', 1));
});
