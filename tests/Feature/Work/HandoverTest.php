<?php

use App\Models\Permit;
use App\Models\RiskAssessment;
use App\Models\ShiftHandover;
use App\Models\Site;
use App\Models\User;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $ra = RiskAssessment::create(['site_id' => $this->site->id, 'type' => 'jsa', 'title' => 'x', 'activity' => 'x', 'review_due_on' => today()->addYear()]);
    $this->active = Permit::create(['type' => 'electrical', 'site_id' => $this->site->id, 'risk_assessment_id' => $ra->id, 'description' => 'x', 'valid_from' => now()->subHour(), 'valid_to' => now()->addHours(5)]);
    $this->active->forceFill(['status' => 'active'])->save();
    $this->active->isolations()->create(['point' => 'DB-3', 'method' => 'Locked', 'lock_no' => 'L-17', 'isolated_at' => now()]);
    $this->active->isolations()->create(['point' => 'DB-4', 'method' => 'Locked', 'isolated_at' => now(), 'removed_at' => now()]);
    $closed = Permit::create(['type' => 'general', 'site_id' => $this->site->id, 'risk_assessment_id' => $ra->id, 'description' => 'x', 'valid_from' => now()->subHours(9), 'valid_to' => now()->subHour()]);
    $closed->forceFill(['status' => 'closed'])->save();
});

test('a handover captures the open permits and the locks still on', function () {
    $this->actingAs($this->userWithRole('supervisor'));
    $next = User::factory()->create()->assignRole('supervisor');

    $this->post(route('handovers.store'), ['site_id' => $this->site->id, 'shift' => 'day', 'to_user_id' => $next->id, 'notes' => 'Wet scaffold boards on level 6.'])
        ->assertSessionHasNoErrors();

    $handover = ShiftHandover::query()->sole();
    expect($handover->number)->toBe('SHO-'.now()->year.'-0001')
        ->and($handover->open_permits)->toHaveCount(1)
        ->and($handover->open_permits[0]['number'])->toBe($this->active->number)
        ->and($handover->open_permits[0]['isolations'])->toBe([['point' => 'DB-3', 'lock_no' => 'L-17']]);
});

test('only the person taking over acknowledges, once; nobody hands over to themselves', function () {
    $outgoing = $this->userWithRole('supervisor');
    $next = User::factory()->create()->assignRole('supervisor');
    $this->actingAs($outgoing);

    $this->post(route('handovers.store'), ['site_id' => $this->site->id, 'shift' => 'night', 'to_user_id' => $outgoing->id, 'notes' => 'x'])->assertSessionHasErrors('to_user_id');
    $this->post(route('handovers.store'), ['site_id' => $this->site->id, 'shift' => 'night', 'to_user_id' => $next->id, 'notes' => 'x']);
    $handover = ShiftHandover::query()->sole();

    $this->put(route('handovers.acknowledge', $handover))->assertSessionHasErrors('status');
    $this->actingAs($next)->put(route('handovers.acknowledge', $handover))->assertSessionHasNoErrors();
    $this->put(route('handovers.acknowledge', $handover))->assertSessionHasErrors('status');

    expect($handover->fresh()->acknowledged_at)->not->toBeNull();
});
