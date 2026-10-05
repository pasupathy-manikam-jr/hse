<?php

use App\Models\Chemical;
use App\Models\RiskAssessment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
});

function coshh(Site $site): RiskAssessment
{
    $ra = RiskAssessment::create(['site_id' => $site->id, 'type' => 'coshh', 'title' => 'Curing compound', 'activity' => 'x', 'review_due_on' => today()->addYear()]);
    $ra->hazards()->create(['hazard' => 'Skin', 'likelihood' => 2, 'severity' => 2, 'residual_likelihood' => 1, 'residual_severity' => 2]);
    $ra->approve(User::factory()->create());

    return $ra;
}

test('a chemical is added with its hazards and SDS file', function () {
    $this->actingAs($this->userWithRole('supervisor'));

    $this->post(route('chemicals.store'), [
        'site_id' => $this->site->id, 'name' => 'Diesel', 'hazards' => ['flammable', 'harmful'], 'max_quantity' => 5000, 'unit' => 'L',
        'sds_issued_on' => today()->subYear()->toDateString(), 'sds' => UploadedFile::fake()->create('diesel-sds.pdf', 40, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $chemical = Chemical::query()->sole();
    expect($chemical)->hazards->toBe(['flammable', 'harmful'])->file_sha256->toHaveLength(64)->sds_outdated->toBeFalse();
    $this->get(route('chemicals.sds', $chemical))->assertOk();
});

test('hazards are GHS pictograms and the COSHH link must be a COSHH assessment at the site', function () {
    $jsa = RiskAssessment::create(['site_id' => $this->site->id, 'type' => 'jsa', 'title' => 'x', 'activity' => 'x', 'review_due_on' => today()->addYear()]);
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->post(route('chemicals.store'), ['site_id' => $this->site->id, 'name' => 'X', 'hazards' => ['spicy'], 'risk_assessment_id' => $jsa->id])
        ->assertSessionHasErrors(['hazards.0', 'risk_assessment_id']);
});

test('a health hazard needs an approved COSHH assessment, and an SDS over 5 years old is flagged', function () {
    $irritant = Chemical::create(['site_id' => $this->site->id, 'name' => 'Curing compound', 'hazards' => ['harmful'], 'sds_issued_on' => today()->subYears(6)]);
    $flammable = Chemical::create(['site_id' => $this->site->id, 'name' => 'Acetylene', 'hazards' => ['flammable']]);

    expect($irritant)->needs_coshh->toBeTrue()->sds_outdated->toBeTrue()
        ->and($flammable->needs_coshh)->toBeFalse();

    $irritant->update(['risk_assessment_id' => coshh($this->site)->id]);
    expect($irritant->fresh()->needs_coshh)->toBeFalse();
});

test('workers can read the register and open an SDS but not change it; other sites stay hidden', function () {
    $chemical = Chemical::create(['site_id' => $this->site->id, 'name' => 'Diesel', 'hazards' => ['flammable']]);
    $chemical->attachUpload(UploadedFile::fake()->create('sds.pdf', 10, 'application/pdf'))->save();
    $elsewhere = Chemical::create(['site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id, 'name' => 'Acid']);
    $worker = $this->userWithRole('worker');
    $worker->update(['site_id' => $this->site->id]);

    $this->actingAs($worker)->get(route('chemicals.index'))
        ->assertInertia(fn (Assert $page) => $page->component('chemicals/index')->has('chemicals.data', 1));
    $this->get(route('chemicals.sds', $chemical))->assertOk();
    $this->get(route('chemicals.sds', $elsewhere))->assertNotFound();
    $this->post(route('chemicals.store'), ['site_id' => $this->site->id, 'name' => 'X'])->assertForbidden();
});
