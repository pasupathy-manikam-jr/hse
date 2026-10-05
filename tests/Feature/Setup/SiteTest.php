<?php

use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the sites page lists sites with their areas', function () {
    $site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $site->areas()->create(['name' => 'Basement']);

    $this->actingAs($this->userWithRole('auditor'))
        ->get(route('sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sites/index')
            ->where('sites.data.0.code', 'KL')
            ->where('sites.data.0.areas.0.name', 'Basement'));
});

test('an hse manager creates, updates and deletes a site', function () {
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->post(route('sites.store'), ['code' => 'SA', 'name' => 'Plant', 'active' => true])->assertSessionHasNoErrors();
    $site = Site::query()->where('code', 'SA')->firstOrFail();

    $this->put(route('sites.update', $site), ['code' => 'SA', 'name' => 'Stamping plant', 'active' => false])->assertSessionHasNoErrors();
    expect($site->fresh())->name->toBe('Stamping plant')->active->toBeFalse();

    $this->delete(route('sites.destroy', $site));
    expect(Site::query()->count())->toBe(0);
});

test('site codes are unique and required', function () {
    Site::create(['code' => 'SA', 'name' => 'Plant']);

    $this->actingAs($this->userWithRole('hse-manager'))
        ->post(route('sites.store'), ['code' => 'SA', 'name' => ''])
        ->assertSessionHasErrors(['code', 'name']);
});

test('a site with users cannot be deleted', function () {
    $site = Site::create(['code' => 'SA', 'name' => 'Plant']);
    User::factory()->create(['site_id' => $site->id]);

    $this->actingAs($this->userWithRole('hse-manager'))->delete(route('sites.destroy', $site));

    expect($site->fresh())->not->toBeNull();
});

test('areas are added once per site and removed only through their own site', function () {
    $site = Site::create(['code' => 'SA', 'name' => 'Plant']);
    $other = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->post(route('sites.areas.store', $site), ['name' => 'Warehouse'])->assertSessionHasNoErrors();
    $this->post(route('sites.areas.store', $site), ['name' => 'Warehouse'])->assertSessionHasErrors('name');
    $this->post(route('sites.areas.store', $other), ['name' => 'Warehouse'])->assertSessionHasNoErrors();

    $area = $site->areas()->firstOrFail();
    $this->delete(route('sites.areas.destroy', [$other, $area]))->assertNotFound();
    $this->delete(route('sites.areas.destroy', [$site, $area]));

    expect($site->areas()->count())->toBe(0)->and($other->areas()->count())->toBe(1);
});

test('read-only roles cannot change sites and workers cannot open them', function () {
    $site = Site::create(['code' => 'SA', 'name' => 'Plant']);

    $this->actingAs($this->userWithRole('auditor'));
    $this->post(route('sites.store'), ['code' => 'X', 'name' => 'X'])->assertForbidden();
    $this->put(route('sites.update', $site), ['code' => 'SA', 'name' => 'X'])->assertForbidden();
    $this->delete(route('sites.destroy', $site))->assertForbidden();
    $this->post(route('sites.areas.store', $site), ['name' => 'X'])->assertForbidden();

    $this->actingAs($this->userWithRole('worker'))->get(route('sites.index'))->assertForbidden();
});
