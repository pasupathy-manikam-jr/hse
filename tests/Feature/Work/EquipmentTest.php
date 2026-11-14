<?php

use App\Models\Equipment;
use App\Models\Site;
use App\Models\User;
use App\Notifications\DailyDigest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
});

function crane(array $overrides = []): Equipment
{
    return Equipment::forceCreate(['site_id' => test()->site->id, 'asset_tag' => 'CR-01', 'name' => 'Mobile crane', 'type' => 'lifting',
        'inspection_interval_months' => 12, 'last_inspected_on' => today()->subMonths(3), ...$overrides]);
}

test('equipment is added, and each inspection is kept with its certificate and sets the due date and status', function () {
    $this->actingAs($this->userWithRole('supervisor'));

    $this->post(route('equipment.store'), [
        'site_id' => $this->site->id, 'asset_tag' => 'SL-14', 'name' => 'Chain sling', 'type' => 'lifting', 'inspection_interval_months' => 6, 'status' => 'in-service',
    ])->assertSessionHasNoErrors();
    $sling = Equipment::query()->sole();
    expect($sling->next_inspection_on)->toBeNull();

    $this->post(route('equipment.inspections.store', $sling), [
        'inspected_on' => '2026-08-31', 'passed' => '1', 'inspector' => 'Allianz', 'certificate' => UploadedFile::fake()->create('loler.pdf', 20, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $first = $sling->inspections()->sole();
    expect($sling->fresh()->next_inspection_on->toDateString())->toBe('2027-02-28')->and($first->file_sha256)->toHaveLength(64);
    $this->get(route('equipment.certificate', [$sling, $first]))->assertOk();

    $this->post(route('equipment.inspections.store', $sling), ['inspected_on' => '2026-11-01', 'passed' => '0', 'inspector' => 'Allianz', 'notes' => 'Link stretched.'])
        ->assertSessionHasNoErrors();
    // An older inspection entered late does not override the latest one.
    $this->post(route('equipment.inspections.store', $sling), ['inspected_on' => '2026-02-01', 'passed' => '1', 'inspector' => 'Allianz']);

    expect($sling->fresh())->status->toBe('out-of-service')->next_inspection_on->toDateString()->toBe('2027-05-01')
        ->and($sling->inspections()->count())->toBe(3);
    $this->post(route('equipment.inspections.store', $sling), ['inspected_on' => today()->addDay()->toDateString()])
        ->assertSessionHasErrors(['inspected_on', 'passed', 'inspector']);
});

test('the tag is unique, the type and interval are checked, and the area and owner must be at the site', function () {
    crane();
    $elsewhere = Site::create(['code' => 'SA', 'name' => 'Plant']);
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->post(route('equipment.store'), [
        'site_id' => $this->site->id, 'asset_tag' => 'CR-01', 'name' => 'X', 'type' => 'rocket', 'inspection_interval_months' => 0, 'status' => 'in-service',
        'area_id' => $elsewhere->areas()->create(['name' => 'Yard'])->id, 'owner_id' => User::factory()->create(['site_id' => $elsewhere->id])->id,
    ])->assertSessionHasErrors(['asset_tag', 'type', 'inspection_interval_months', 'area_id', 'owner_id']);
});

test('the list filters overdue equipment; other sites stay hidden and workers cannot open it', function () {
    crane();
    crane(['asset_tag' => 'FE-07', 'type' => 'fire', 'inspection_interval_months' => 6, 'last_inspected_on' => today()->subMonths(7)]);
    crane(['asset_tag' => 'NEW-1', 'last_inspected_on' => null]);
    $hidden = crane(['asset_tag' => 'SA-1', 'site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id]);
    $supervisor = $this->userWithRole('supervisor');
    $supervisor->update(['site_id' => $this->site->id]);

    $this->actingAs($supervisor)->get(route('equipment.index', ['due' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page->component('equipment/index')->has('equipment.data', 2));
    $this->post(route('equipment.inspections.store', $hidden), ['inspected_on' => today()->toDateString(), 'passed' => '1', 'inspector' => 'x'])->assertNotFound();
    $this->post(route('equipment.update', $hidden), [])->assertNotFound();
    $this->delete(route('equipment.destroy', crane(['asset_tag' => 'X-1'])))->assertForbidden();

    $this->actingAs($this->userWithRole('worker'))->get(route('equipment.index'))->assertForbidden();
});

test('owners are reminded of inspections due within 30 days or overdue', function () {
    Notification::fake();
    $owner = User::factory()->create();
    crane(['owner_id' => $owner->id, 'last_inspected_on' => today()->subMonths(12)->addDays(10)]);
    crane(['asset_tag' => 'OK-1', 'owner_id' => $owner->id]);
    crane(['asset_tag' => 'OFF-1', 'owner_id' => User::factory()->create()->id, 'status' => 'out-of-service', 'last_inspected_on' => null]);

    $this->artisan('hse:reminders')->assertSuccessful();

    Notification::assertSentTo($owner, DailyDigest::class, fn (DailyDigest $n) => $n->sections['Equipment inspections'] === ['CR-01 Mobile crane (due '.today()->addDays(10)->toDateString().')']);
    Notification::assertCount(1);
});
