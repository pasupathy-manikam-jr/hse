<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard offers the Android app only when its APK is on the server', function () {
    $user = User::factory()->create();
    $apk = public_path('downloads/hse.apk');
    $existed = is_file($apk);

    if ($existed) {
        rename($apk, "{$apk}.bak");
    }

    try {
        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('androidApp', null));

        @mkdir(dirname($apk), recursive: true);
        file_put_contents($apk, 'apk');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('androidApp', asset('downloads/hse.apk')));
    } finally {
        @unlink($apk);
        if ($existed) {
            rename("{$apk}.bak", $apk);
        }
    }
});
