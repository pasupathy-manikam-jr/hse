<?php

use App\Models\Contractor;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the contractors page lists and filters by approval', function () {
    Contractor::create(['name' => 'Bina Scaffold', 'approved' => true]);
    Contractor::create(['name' => 'Kilat Electrical']);

    $this->actingAs($this->userWithRole('auditor'))
        ->get(route('contractors.index', ['approved' => '0']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('contractors/index')
            ->has('contractors.data', 1)
            ->where('contractors.data.0.name', 'Kilat Electrical'));
});

test('a supervisor creates and updates a contractor but cannot approve it', function () {
    $this->actingAs($this->userWithRole('supervisor'));

    $this->post(route('contractors.store'), ['name' => 'Kilat Electrical', 'insurance_expires_on' => '2027-01-31'])->assertSessionHasNoErrors();
    $contractor = Contractor::query()->firstOrFail();
    expect($contractor->approved)->toBeFalse()->and($contractor->created_by)->toBe(auth()->id());

    $this->put(route('contractors.update', $contractor), ['name' => 'Kilat Electrical Sdn Bhd', 'email' => 'not-an-email'])->assertSessionHasErrors('email');
    $this->put(route('contractors.update', $contractor), ['name' => 'Kilat Electrical Sdn Bhd'])->assertSessionHasNoErrors();

    $this->put(route('contractors.approval', $contractor))->assertForbidden();
    $this->delete(route('contractors.destroy', $contractor))->assertForbidden();
});

test('an hse manager approves and withdraws approval', function () {
    $contractor = Contractor::create(['name' => 'Kilat Electrical']);
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->put(route('contractors.approval', $contractor));
    expect($contractor->fresh()->approved)->toBeTrue();

    $this->put(route('contractors.approval', $contractor));
    expect($contractor->fresh()->approved)->toBeFalse();
});

test('a contractor with workers cannot be deleted', function () {
    $contractor = Contractor::create(['name' => 'Kilat Electrical']);
    User::factory()->create(['contractor_id' => $contractor->id]);

    $this->actingAs($this->userWithRole('hse-manager'))->delete(route('contractors.destroy', $contractor));

    expect($contractor->exists())->toBeTrue();
});

test('a contractor can work only when approved and insured through today', function (bool $approved, ?string $expires, bool $canWork) {
    $contractor = new Contractor(['approved' => $approved, 'insurance_expires_on' => $expires]);

    expect($contractor->canWork())->toBe($canWork);
})->with([
    'approved, insured' => [true, '+1 month', true],
    'approved, expires today' => [true, 'today', true],
    'approved, expired yesterday' => [true, 'yesterday', false],
    'approved, no insurance' => [true, null, false],
    'not approved' => [false, '+1 month', false],
]);
