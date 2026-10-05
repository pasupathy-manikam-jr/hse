<?php

use App\Models\InternalAudit;
use App\Models\IsoClause;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\IsoClauseSeeder;

beforeEach(function () {
    $this->seed(IsoClauseSeeder::class);
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->clause = IsoClause::query()->where('number', '7.2')->sole();
});

function planAudit(): InternalAudit
{
    test()->post(route('audits.store'), [
        'site_id' => test()->site->id, 'title' => 'Q4 audit', 'lead_auditor_id' => User::factory()->create()->id,
        'planned_on' => today()->toDateString(), 'clause_ids' => [test()->clause->id],
    ])->assertSessionHasNoErrors();

    return InternalAudit::query()->sole();
}

test('the ISO 45001 clauses are seeded', function () {
    expect(IsoClause::query()->count())->toBe(29)
        ->and(IsoClause::query()->where('number', '9.2')->value('title'))->toBe('Internal audit');
});

test('an auditor plans and starts an audit; findings only while it is in progress', function () {
    $this->actingAs($this->userWithRole('auditor'));
    $audit = planAudit();

    expect($audit)->number->toBe('AUD-'.now()->year.'-0001')->status->toBe('planned');
    $this->post(route('audits.findings.store', $audit), ['type' => 'observation', 'description' => 'x'])->assertSessionHasErrors('status');

    $this->put(route('audits.start', $audit));
    $this->post(route('audits.findings.store', $audit), ['type' => 'observation', 'description' => 'Paper records only.'])->assertSessionHasNoErrors();

    expect($audit->findings()->sole()->action_id)->toBeNull();
});

test('a nonconformity needs an owner and due date, and raises a corrective action', function () {
    $this->actingAs($this->userWithRole('auditor'));
    $audit = planAudit();
    $audit->start();
    $owner = User::factory()->create();

    $this->post(route('audits.findings.store', $audit), ['type' => 'major-nonconformity', 'description' => 'No training record.'])->assertSessionHasErrors(['owner_id', 'due_on']);
    $this->post(route('audits.findings.store', $audit), [
        'type' => 'major-nonconformity', 'iso_clause_id' => $this->clause->id, 'description' => 'No training record.',
        'owner_id' => $owner->id, 'due_on' => today()->addWeek()->toDateString(),
    ])->assertSessionHasNoErrors();

    $action = $audit->findings()->sole()->action;
    expect($action)->not->toBeNull()
        ->owner_id->toBe($owner->id)->priority->toBe('high')->site_id->toBe($this->site->id)
        ->and($action->description)->toContain('§7.2');
});

test('completing needs a summary', function () {
    $this->actingAs($this->userWithRole('auditor'));
    $audit = planAudit();
    $audit->start();

    $this->put(route('audits.complete', $audit), ['summary' => ''])->assertSessionHasErrors('summary');
    $this->put(route('audits.complete', $audit), ['summary' => 'Generally good.'])->assertSessionHasNoErrors();

    expect($audit->fresh())->status->toBe('completed')->completed_at->not->toBeNull();
});

test('workers cannot open audits', function () {
    $this->actingAs($this->userWithRole('worker'))->get(route('audits.index'))->assertForbidden();
});
