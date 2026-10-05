<?php

use App\Models\Contractor;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins see users with their role, site and employer', function () {
    $this->actingAs($this->userWithRole());

    $this->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('users/index')
            ->has('users.data', 1)
            ->has('roles', 6)
            ->where('users.data.0.roles.0.name', 'admin'));
});

test('an admin creates a verified user with one role, a site and an employer', function () {
    $site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $contractor = Contractor::create(['name' => 'Bina Scaffold']);
    $this->actingAs($this->userWithRole());

    $this->post(route('users.store'), [
        'name' => 'Siti', 'email' => 'siti@example.com', 'password' => 'Str0ng-pass!word', 'password_confirmation' => 'Str0ng-pass!word',
        'role' => 'worker', 'site_id' => $site->id, 'contractor_id' => $contractor->id,
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'siti@example.com')->firstOrFail();
    expect($user->hasRole('worker'))->toBeTrue()
        ->and($user->site_id)->toBe($site->id)
        ->and($user->contractor_id)->toBe($contractor->id)
        ->and($user->email_verified_at)->not->toBeNull();
});

test('a role must exist and emails stay unique', function () {
    $admin = $this->userWithRole();

    $this->actingAs($admin)
        ->post(route('users.store'), ['name' => 'X', 'email' => $admin->email, 'password' => 'Str0ng-pass!word', 'role' => 'owner'])
        ->assertSessionHasErrors(['email', 'role']);
});

test('updating a user replaces their role', function () {
    $this->actingAs($this->userWithRole());
    $user = User::factory()->create()->assignRole('worker');

    $this->put(route('users.update', $user), ['name' => $user->name, 'email' => $user->email, 'role' => 'supervisor'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->getRoleNames()->all())->toBe(['supervisor']);
});

test('the last admin cannot be demoted or deleted, and nobody deletes themselves', function () {
    $admin = $this->userWithRole();
    $this->actingAs($admin);

    $this->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'worker'])
        ->assertSessionHasErrors('role');
    $this->delete(route('users.destroy', $admin));

    expect($admin->fresh()->trashed())->toBeFalse()->and($admin->hasRole('admin'))->toBeTrue();
});

test('deleted users are soft deleted', function () {
    $this->actingAs($this->userWithRole());
    $user = User::factory()->create()->assignRole('worker');

    $this->delete(route('users.destroy', $user));

    expect(User::withTrashed()->find($user->id)?->trashed())->toBeTrue();
});

test('only admins manage users', function () {
    $this->actingAs($this->userWithRole('hse-manager'))->get(route('users.index'))->assertForbidden();
});
