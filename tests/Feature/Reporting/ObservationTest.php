<?php

use App\Models\AuditLog;
use App\Models\Observation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->area = $this->site->areas()->create(['name' => 'Crane zone']);
});

function reportData(array $overrides = []): array
{
    return [
        'site_id' => test()->site->id, 'area_id' => test()->area->id, 'type' => 'near-miss', 'potential' => 'high',
        'description' => 'Load slipped from the sling.', 'observed_at' => now()->subHour()->toIso8601String(), ...$overrides,
    ];
}

function makeObservation(array $overrides = []): Observation
{
    return Observation::create([...reportData(), 'reporter_id' => null, ...$overrides]);
}

test('a worker reports a near miss from the phone form, with photos and location', function () {
    Storage::fake('local');
    $worker = $this->userWithRole('worker');
    $worker->update(['site_id' => $this->site->id]);

    $this->actingAs($worker)->get(route('observations.create'))
        ->assertInertia(fn (Assert $page) => $page->component('observations/create')->has('sites', 1)->where('defaultSiteId', $this->site->id));

    $this->post(route('observations.store'), reportData([
        'latitude' => '3.1579', 'longitude' => '101.7116',
        'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
    ]))->assertRedirect(route('observations.create'));

    $observation = Observation::query()->sole();
    expect($observation->number)->toBe('OBS-'.now()->year.'-0001')
        ->and($observation->status)->toBe('open')
        ->and($observation->reporter_id)->toBe($worker->id)
        ->and($observation->photos)->toHaveCount(2)
        ->and($observation->photos->first()->sha256)->toHaveLength(64);
    Storage::disk('local')->assertExists($observation->photos->first()->path);
});

test('an anonymous report stores no reporter and no actor in the audit trail', function () {
    $worker = $this->userWithRole('worker');

    $this->actingAs($worker)->post(route('observations.store'), reportData(['anonymous' => true]))->assertSessionHasNoErrors();

    $observation = Observation::query()->sole();
    $log = AuditLog::query()->where('auditable_type', $observation->getMorphClass())->sole();
    expect($observation->reporter_id)->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->ip_address)->toBeNull()
        ->and($log->new_values)->not->toHaveKey('reporter_id', $worker->id);
});

test('reports are validated: area of another site, future time, too many photos, half a location', function () {
    $other = Site::create(['code' => 'SA', 'name' => 'Plant'])->areas()->create(['name' => 'Warehouse']);
    $this->actingAs($this->userWithRole('worker'));

    $this->post(route('observations.store'), reportData([
        'area_id' => $other->id, 'type' => 'rumour', 'observed_at' => now()->addDay()->toIso8601String(),
        'latitude' => '3.1', 'photos' => array_fill(0, 6, UploadedFile::fake()->image('x.jpg')),
    ]))->assertSessionHasErrors(['area_id', 'type', 'observed_at', 'longitude', 'photos']);

    $this->post(route('observations.store'), reportData(['photos' => [UploadedFile::fake()->create('virus.exe', 10)]]))
        ->assertSessionHasErrors('photos.0');
});

test('a user with a home site can only report on that site', function () {
    $other = Site::create(['code' => 'SA', 'name' => 'Plant']);
    $worker = $this->userWithRole('worker');
    $worker->update(['site_id' => $this->site->id]);

    $this->actingAs($worker)->post(route('observations.store'), reportData(['site_id' => $other->id, 'area_id' => null]))
        ->assertSessionHasErrors('site_id');
});

test('the list is scoped to the user\'s site and filtered by status', function () {
    $other = Site::create(['code' => 'SA', 'name' => 'Plant']);
    makeObservation();
    makeObservation(['site_id' => $other->id, 'area_id' => null]);
    $supervisor = $this->userWithRole('supervisor');
    $supervisor->update(['site_id' => $this->site->id]);

    $this->actingAs($supervisor)->get(route('observations.index'))
        ->assertInertia(fn (Assert $page) => $page->component('observations/index')
            ->has('observations.data', 1)
            ->where('counts', ['all' => 1, 'open' => 1, 'actioned' => 0, 'closed' => 0]));

    $this->get(route('observations.index', ['status' => 'closed']))
        ->assertInertia(fn (Assert $page) => $page->has('observations.data', 0));

    $this->get(route('observations.show', Observation::query()->where('site_id', $other->id)->sole()))->assertNotFound();
});

test('workers report but cannot browse observations', function () {
    $this->actingAs($this->userWithRole('worker'));

    $this->get(route('observations.index'))->assertForbidden();
    $this->get(route('observations.show', makeObservation()))->assertForbidden();
});

test('photos are served only to people who may open the observation', function () {
    Storage::fake('local');
    $observation = makeObservation();
    $observation->attachPhotos([UploadedFile::fake()->image('a.jpg')]);
    $photo = $observation->photos()->sole();

    $this->actingAs($this->userWithRole('auditor'))->get(route('photos.show', $photo))->assertOk();
    $this->actingAs($this->userWithRole('worker'))->get(route('photos.show', $photo))->assertNotFound();

    $elsewhere = $this->userWithRole('supervisor');
    $elsewhere->update(['site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id]);
    $this->actingAs($elsewhere)->get(route('photos.show', $photo))->assertNotFound();
});

test('raising the first action marks the observation actioned; it closes only when every action is verified', function () {
    $observation = makeObservation();
    $owner = User::factory()->create();
    $this->actingAs($supervisor = $this->userWithRole('supervisor'));

    $this->post(route('observations.actions.store', $observation), [
        'description' => 'Barricade the swing radius.', 'control_level' => 'engineering', 'priority' => 'high',
        'owner_id' => $owner->id, 'due_on' => today()->addWeek()->toDateString(),
    ])->assertSessionHasNoErrors();

    $action = $observation->actions()->sole();
    expect($observation->fresh()->status)->toBe('actioned')
        ->and($action->site_id)->toBe($this->site->id)
        ->and($action->created_by)->toBe($supervisor->id);

    $this->put(route('observations.close', $observation))->assertSessionHasErrors('status');

    $action->transitionTo('done', $owner, 'Barriers installed.');
    $action->transitionTo('verified', $supervisor);
    $this->put(route('observations.close', $observation))->assertSessionHasNoErrors();

    expect($observation->fresh())->status->toBe('closed')->closed_by->toBe($supervisor->id);

    $this->post(route('observations.actions.store', $observation), [
        'description' => 'Too late.', 'control_level' => 'ppe', 'priority' => 'low', 'owner_id' => $owner->id, 'due_on' => today()->toDateString(),
    ])->assertSessionHasErrors('description');
});

test('an action needs a real owner and a due date that is not in the past', function () {
    $this->actingAs($this->userWithRole('supervisor'))
        ->post(route('observations.actions.store', makeObservation()), [
            'description' => 'x', 'control_level' => 'prayer', 'priority' => 'low', 'owner_id' => 999, 'due_on' => today()->subDay()->toDateString(),
        ])->assertSessionHasErrors(['control_level', 'owner_id', 'due_on']);
});

test('an observation with actions cannot be deleted; one without is deleted with its photos', function () {
    Storage::fake('local');
    $withAction = makeObservation();
    $withAction->raiseAction(['description' => 'x', 'control_level' => 'ppe', 'priority' => 'low', 'owner_id' => User::factory()->create()->id, 'due_on' => today()]);
    $plain = makeObservation();
    $plain->attachPhotos([UploadedFile::fake()->image('a.jpg')]);
    $path = $plain->photos()->sole()->path;
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->delete(route('observations.destroy', $withAction));
    $this->delete(route('observations.destroy', $plain));

    expect($withAction->fresh())->not->toBeNull()->and($plain->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

test('a queued report sent twice with the same client_ref is stored once', function () {
    $this->actingAs($this->userWithRole('worker'));
    $ref = (string) Str::uuid();

    $this->post(route('observations.store'), reportData(['client_ref' => $ref]))->assertSessionHasNoErrors();
    $this->post(route('observations.store'), reportData(['client_ref' => $ref]))->assertRedirect(route('observations.create'));
    $this->post(route('observations.store'), reportData(['client_ref' => 'not-a-uuid']))->assertSessionHasErrors('client_ref');

    expect(Observation::query()->count())->toBe(1)
        ->and(Observation::query()->sole()->client_ref)->toBe($ref);
});
