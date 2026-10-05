<?php

use App\Models\Competency;
use App\Models\Contractor;
use App\Models\Permit;
use App\Models\RiskAssessment;
use App\Models\Site;
use App\Models\User;
use App\Models\UserCompetency;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->area = $this->site->areas()->create(['name' => 'Basement']);
    $this->ra = RiskAssessment::create(['site_id' => $this->site->id, 'type' => 'jsa', 'title' => 'Sump', 'activity' => 'x', 'review_due_on' => today()->addYear()]);
    $this->ra->hazards()->create(['hazard' => 'Gas', 'likelihood' => 3, 'severity' => 5, 'residual_likelihood' => 1, 'residual_severity' => 5]);
    $this->ra->approve(User::factory()->create());
    $this->worker = User::factory()->create(['name' => 'Ali']);
    $this->requester = $this->userWithRole('supervisor');
    $this->issuer = User::factory()->create(['password' => Hash::make('secret-pass')])->assignRole('permit-issuer');
});

function permitData(array $overrides = []): array
{
    return [
        'type' => 'work-at-height', 'site_id' => test()->site->id, 'area_id' => test()->area->id, 'risk_assessment_id' => test()->ra->id,
        'description' => 'Fix guardrails.', 'valid_from' => now()->toIso8601String(), 'valid_to' => now()->addHours(6)->toIso8601String(),
        'worker_ids' => [test()->worker->id], ...$overrides,
    ];
}

/**
 * A requested permit with every precaution ticked, created as the requester.
 */
function requestPermit(array $overrides = []): Permit
{
    test()->actingAs(test()->requester)->post(route('permits.store'), permitData($overrides))->assertSessionHasNoErrors();
    $permit = Permit::query()->latest('id')->firstOrFail();
    $permit->update(['precautions' => array_fill_keys(array_keys(Permit::precautionsFor($permit->type)), true)]);

    return $permit;
}

function approve(Permit $permit, string $password = 'secret-pass')
{
    return test()->actingAs(test()->issuer)->put(route('permits.transition', $permit), ['status' => 'approved', 'password' => $password]);
}

function hold(User $user, string $type, ?string $expires): void
{
    $competency = Competency::query()->firstOrCreate(['name' => $type], ['permit_type' => $type, 'validity_months' => 24]);
    UserCompetency::create(['user_id' => $user->id, 'competency_id' => $competency->id, 'issued_on' => today()->subYear(), 'expires_on' => $expires]);
}

test('a request is one shift at most, under an approved assessment at its site, with workers', function () {
    $other = RiskAssessment::create(['site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id, 'type' => 'jsa', 'title' => 'x', 'activity' => 'x', 'review_due_on' => today()->addYear()]);
    $this->actingAs($this->requester);

    $this->post(route('permits.store'), permitData(['valid_to' => now()->addHours(13)->toIso8601String()]))->assertSessionHasErrors('valid_to');
    $this->post(route('permits.store'), permitData(['risk_assessment_id' => $other->id, 'worker_ids' => []]))->assertSessionHasErrors(['risk_assessment_id', 'worker_ids']);

    $this->post(route('permits.store'), permitData())->assertSessionHasNoErrors();
    expect(Permit::query()->sole())->number->toBe('PTW-'.now()->year.'-0001')->status->toBe('requested')
        ->and(Permit::query()->sole()->workers->pluck('id')->all())->toBe([$this->worker->id]);
});

test('approval is signed by re-entering the password, by someone other than the requester', function () {
    $permit = requestPermit();

    approve($permit, 'wrong')->assertSessionHasErrors('password');
    expect($permit->fresh()->status)->toBe('requested')->and($permit->signatures()->count())->toBe(0);

    $this->requester->assignRole('permit-issuer');
    $this->actingAs($this->requester)->put(route('permits.transition', $permit), ['status' => 'approved', 'password' => 'password'])->assertSessionHasErrors('status');

    approve($permit)->assertSessionHasNoErrors();
    $signature = $permit->signatures()->sole();
    expect($permit->fresh())->status->toBe('approved')->approved_by->toBe($this->issuer->id)
        ->and($signature)->meaning->toBe('approved')->signer_name->toBe($this->issuer->name);
});

test('only permit issuers can approve', function () {
    $permit = requestPermit();
    $supervisor = User::factory()->create()->assignRole('supervisor');

    $this->actingAs($supervisor)->put(route('permits.transition', $permit), ['status' => 'approved', 'password' => 'password'])->assertForbidden();
});

test('a worker without the competency for the whole permit blocks approval', function () {
    $permit = requestPermit(['type' => 'hot-work']);
    hold($this->worker, 'hot-work', now()->addHours(2)->toDateString() === today()->toDateString() ? today()->toDateString() : today()->addDay()->toDateString());
    $permit->update(['valid_to' => now()->addDays(2)]);

    approve($permit)->assertSessionHasErrors('status');
    expect($permit->fresh()->approvalProblems())->toContain('No valid competency: Ali: hot-work');

    UserCompetency::query()->update(['expires_on' => today()->addYear()]);
    expect($permit->fresh()->approvalProblems())->toBe([]);
});

test('unticked precautions and an unapproved contractor block approval', function () {
    $permit = requestPermit(['contractor_id' => Contractor::create(['name' => 'Kilat'])->id]);
    $permit->update(['precautions' => ['briefed' => true]]);

    expect(implode(' ', $permit->fresh()->approvalProblems()))
        ->toContain('Kilat is not approved')
        ->toContain('Confirm every precaution');
});

test('precautions only accept the keys for the permit type', function () {
    $permit = requestPermit();

    $this->actingAs($this->requester)->put(route('permits.precautions', $permit), ['checked' => ['briefed', 'fire_watch']])->assertSessionHasErrors('checked.1');
    $this->put(route('permits.precautions', $permit), ['checked' => ['briefed', 'edge_protection']])->assertSessionHasNoErrors();

    expect($permit->fresh()->precautions)->toBe(['briefed' => true, 'edge_protection' => true]);
});

test('gas tests pass only inside the confined-space limits', function (array $reading, bool $passed) {
    $permit = Permit::create([...collect(permitData(['type' => 'confined-space']))->except('worker_ids')->all()]);

    expect($permit->gasTests()->create(['tested_at' => now(), ...$reading])->passed)->toBe($passed);
})->with([
    'normal air' => [['oxygen' => 20.9, 'lel' => 0, 'h2s' => 0, 'co' => 0], true],
    'oxygen at the low limit' => [['oxygen' => 19.5, 'lel' => 0, 'h2s' => 0, 'co' => 0], true],
    'oxygen deficient' => [['oxygen' => 19.4, 'lel' => 0, 'h2s' => 0, 'co' => 0], false],
    'oxygen enriched' => [['oxygen' => 23.6, 'lel' => 0, 'h2s' => 0, 'co' => 0], false],
    'LEL at 10%' => [['oxygen' => 20.9, 'lel' => 10, 'h2s' => 0, 'co' => 0], false],
    'H2S just under' => [['oxygen' => 20.9, 'lel' => 0, 'h2s' => 9.9, 'co' => 0], true],
    'CO at 25 ppm' => [['oxygen' => 20.9, 'lel' => 0, 'h2s' => 0, 'co' => 25], false],
]);

test('confined space needs a passing gas test to approve and a recent one to start', function () {
    hold($this->worker, 'confined-space', today()->addYear()->toDateString());
    $permit = requestPermit(['type' => 'confined-space']);

    approve($permit)->assertSessionHasErrors('status');
    $this->actingAs($this->requester)->post(route('permits.gas-tests.store', $permit), ['oxygen' => 20.9, 'lel' => 0, 'h2s' => 0, 'co' => 0])->assertSessionHasNoErrors();
    approve($permit)->assertSessionHasNoErrors();

    $permit->gasTests()->update(['tested_at' => now()->subHours(3)]);
    $this->actingAs($this->requester)->put(route('permits.transition', $permit), ['status' => 'active'])->assertSessionHasErrors('status');

    $this->post(route('permits.gas-tests.store', $permit), ['oxygen' => 20.9, 'lel' => 0, 'h2s' => 0, 'co' => 0]);
    $this->put(route('permits.transition', $permit), ['status' => 'active'])->assertSessionHasNoErrors();
    expect($permit->fresh()->status)->toBe('active');
});

test('work only starts inside the permit window', function () {
    $permit = requestPermit(['valid_from' => now()->addHours(2)->toIso8601String(), 'valid_to' => now()->addHours(5)->toIso8601String()]);
    approve($permit);

    $this->actingAs($this->requester)->put(route('permits.transition', $permit), ['status' => 'active'])->assertSessionHasErrors('status');
});

test('suspending needs a reason; a permit with a lock still on cannot close; closing is signed', function () {
    $permit = requestPermit();
    approve($permit);
    $this->actingAs($this->requester);
    $this->put(route('permits.transition', $permit), ['status' => 'active']);

    $this->put(route('permits.transition', $permit), ['status' => 'suspended'])->assertSessionHasErrors('status');
    $this->put(route('permits.transition', $permit), ['status' => 'suspended', 'reason' => 'High winds.'])->assertSessionHasNoErrors();
    $this->put(route('permits.transition', $permit), ['status' => 'active'])->assertSessionHasNoErrors();

    $this->post(route('permits.isolations.store', $permit), ['point' => 'DB-3 breaker 4', 'method' => 'Off, locked, tagged', 'lock_no' => 'L-17']);
    $isolation = $permit->isolations()->sole();
    $this->put(route('permits.transition', $permit), ['status' => 'closed', 'password' => 'password'])->assertSessionHasErrors('status');

    $this->put(route('permits.isolations.remove', [$permit, $isolation]));
    $this->put(route('permits.transition', $permit), ['status' => 'closed', 'password' => 'nope'])->assertSessionHasErrors('password');
    $this->put(route('permits.transition', $permit), ['status' => 'closed', 'password' => 'password'])->assertSessionHasNoErrors();

    expect($permit->fresh())->status->toBe('closed')->closed_at->not->toBeNull()
        ->and($permit->signatures()->pluck('meaning')->all())->toBe(['approved', 'closed'])
        ->and($isolation->fresh()->removed_by)->toBe($this->requester->id);
});

test('cancelling needs a reason, and a closed permit takes no more isolations', function () {
    $permit = requestPermit();
    $this->actingAs($this->requester);

    $this->put(route('permits.transition', $permit), ['status' => 'cancelled'])->assertSessionHasErrors('status');
    $this->put(route('permits.transition', $permit), ['status' => 'cancelled', 'reason' => 'Job postponed.'])->assertSessionHasNoErrors();
    $this->post(route('permits.isolations.store', $permit), ['point' => 'x', 'method' => 'x'])->assertSessionHasErrors('status');
});

test('hot work and confined space in the same area at overlapping times are flagged', function () {
    $tank = requestPermit(['type' => 'confined-space', 'valid_from' => now()->toIso8601String(), 'valid_to' => now()->addHours(4)->toIso8601String()]);
    $weld = requestPermit(['type' => 'hot-work', 'valid_from' => now()->addHours(3)->toIso8601String(), 'valid_to' => now()->addHours(6)->toIso8601String()]);
    $later = requestPermit(['type' => 'hot-work', 'valid_from' => now()->addHours(5)->toIso8601String(), 'valid_to' => now()->addHours(7)->toIso8601String()]);

    expect($tank->conflicts()->pluck('id')->all())->toBe([$weld->id])
        ->and($later->conflicts())->toBeEmpty();

    $this->actingAs($this->requester)->get(route('permits.show', $weld))
        ->assertInertia(fn (Assert $page) => $page->component('permits/show')->where('conflicts.0.number', $tank->number));
});

test('only a requested permit can be edited or deleted', function () {
    $permit = requestPermit();
    approve($permit);

    $this->actingAs($this->requester)->put(route('permits.update', $permit), permitData(['site_id' => null]))->assertSessionHasErrors('status');
    $this->actingAs($this->userWithRole('hse-manager'))->delete(route('permits.destroy', $permit))->assertSessionHasErrors('status');
});
