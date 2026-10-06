<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('login screen hides demo logins by default', function () {
    config(['app.demo_logins' => false]);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('demoLogins', []));
});

test('login screen offers one demo login per role when enabled', function () {
    config(['app.demo_logins' => true, 'app.demo_password' => 'demo-secret']);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->has('demoLogins', 6)
        ->where('demoLogins.0', ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'demo-secret'])
        ->where('demoLogins.1.name', 'HSE Manager'));
});

test('seeded demo accounts can log in with the demo password', function () {
    Storage::fake();
    config(['app.demo_password' => 'demo-secret']);
    $this->seed(DatabaseSeeder::class);

    $this->post(route('login.store'), ['email' => 'worker@example.com', 'password' => 'demo-secret']);

    $this->assertAuthenticated();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
