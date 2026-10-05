<?php

use App\Models\Incident;
use App\Models\RiskAssessment;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
});

function assessment(array $overrides = []): RiskAssessment
{
    return RiskAssessment::create([
        'site_id' => test()->site->id, 'type' => 'hira', 'title' => 'Scaffold', 'activity' => 'Erecting scaffold.',
        'review_due_on' => today()->addYear(), ...$overrides,
    ]);
}

function hazard(RiskAssessment $assessment, int $residualLikelihood = 2, int $residualSeverity = 3): void
{
    $assessment->hazards()->create([
        'hazard' => 'Fall from height', 'likelihood' => 4, 'severity' => 5,
        'residual_likelihood' => $residualLikelihood, 'residual_severity' => $residualSeverity,
    ]);
}

test('scores fall into the matrix bands', function (int $score, string $band) {
    expect(RiskAssessment::band($score))->toBe($band);
})->with([[1, 'low'], [4, 'low'], [5, 'medium'], [9, 'medium'], [10, 'high'], [14, 'high'], [15, 'extreme'], [25, 'extreme']]);

test('a supervisor creates a draft and scores hazards', function () {
    $this->actingAs($supervisor = $this->userWithRole('supervisor'));

    $this->post(route('risk-assessments.store'), [
        'site_id' => $this->site->id, 'type' => 'jsa', 'title' => 'Crane lifts', 'activity' => 'Lifting rebar.', 'review_due_on' => today()->addMonths(6)->toDateString(),
    ])->assertRedirect();

    $ra = RiskAssessment::query()->sole();
    expect($ra)->number->toBe('RA-'.now()->year.'-0001')->status->toBe('draft')->revision->toBe(1)->created_by->toBe($supervisor->id);

    $this->post(route('risk-assessments.hazards.store', $ra), ['hazard' => 'Load falls', 'likelihood' => 6, 'severity' => 0, 'residual_likelihood' => 2, 'residual_severity' => 5])
        ->assertSessionHasErrors(['likelihood', 'severity']);
    $this->post(route('risk-assessments.hazards.store', $ra), ['hazard' => 'Load falls', 'likelihood' => 3, 'severity' => 5, 'residual_likelihood' => 2, 'residual_severity' => 5])
        ->assertSessionHasNoErrors();

    expect($ra->hazards()->sole())->initial_score->toBe(15)->residual_score->toBe(10);
});

test('approval needs another person, a hazard, and every residual score below 15', function () {
    $author = $this->userWithRole('hse-manager');
    $this->actingAs($author);
    $ra = assessment();
    $approver = User::factory()->create()->assignRole('hse-manager');

    $this->actingAs($approver)->put(route('risk-assessments.approve', $ra))->assertSessionHasErrors('status');
    hazard($ra, 3, 5);
    $this->put(route('risk-assessments.approve', $ra))->assertSessionHasErrors('status');
    $this->actingAs($author)->put(route('risk-assessments.approve', $ra->fresh()))->assertSessionHasErrors('status');

    $ra->hazards()->update(['residual_likelihood' => 2]);
    $this->actingAs($approver)->put(route('risk-assessments.approve', $ra->fresh()))->assertSessionHasNoErrors();

    expect($ra->fresh())->status->toBe('approved')->approved_by->toBe($approver->id);
});

test('a new revision copies the hazards; approving it supersedes the old one and clears the review flag', function () {
    $ra = assessment();
    hazard($ra);
    $ra->approve($this->userWithRole('hse-manager'));
    $ra->flagForReview('INC-1 happened.');
    $this->actingAs($supervisor = $this->userWithRole('supervisor'));

    $this->post(route('risk-assessments.revise', $ra))->assertRedirect();
    $this->post(route('risk-assessments.revise', $ra))->assertSessionHasErrors('status');

    $draft = RiskAssessment::query()->where('revision', 2)->sole();
    expect($draft)->number->toBe($ra->number)->status->toBe('draft')->previous_id->toBe($ra->id)->created_by->toBe($supervisor->id)
        ->and($draft->hazards)->toHaveCount(1);

    $this->put(route('risk-assessments.hazards.update', [$ra, $ra->hazards()->sole()]), ['hazard' => 'x', 'likelihood' => 1, 'severity' => 1, 'residual_likelihood' => 1, 'residual_severity' => 1])
        ->assertSessionHasErrors('status');

    $draft->approve(User::factory()->create());
    expect($ra->fresh()->status)->toBe('superseded')
        ->and($draft->fresh())->status->toBe('approved')->review_required->toBeFalse();
});

test('linking an incident flags the assessment for review, which an approver can clear', function () {
    $ra = assessment();
    hazard($ra);
    $ra->approve(User::factory()->create());
    $incident = Incident::create(['site_id' => $this->site->id, 'type' => 'injury', 'title' => 'Crush', 'description' => 'x', 'occurred_at' => now()]);
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->put(route('incidents.update', $incident), [
        'type' => 'injury', 'title' => 'Crush', 'description' => 'x', 'occurred_at' => now()->subMinute()->toIso8601String(), 'risk_assessment_id' => $ra->id,
    ])->assertSessionHasNoErrors();

    expect($ra->fresh())->review_required->toBeTrue()->review_reason->toContain($incident->number);
    $this->get(route('risk-assessments.index', ['review' => 'required']))->assertInertia(fn (Assert $page) => $page->has('assessments.data', 1));

    $this->put(route('risk-assessments.clear-review', $ra))->assertSessionHasNoErrors();
    expect($ra->fresh()->review_required)->toBeFalse();
});

test('an incident can only be linked to an approved assessment at its own site', function () {
    $draft = assessment();
    $elsewhere = assessment(['site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id]);
    hazard($elsewhere);
    $elsewhere->approve(User::factory()->create());
    $incident = Incident::create(['site_id' => $this->site->id, 'type' => 'injury', 'title' => 'Crush', 'description' => 'x', 'occurred_at' => now()]);
    $this->actingAs($this->userWithRole('hse-manager'));

    foreach ([$draft, $elsewhere] as $ra) {
        $this->put(route('incidents.update', $incident), [
            'type' => 'injury', 'title' => 'Crush', 'description' => 'x', 'occurred_at' => now()->subMinute()->toIso8601String(), 'risk_assessment_id' => $ra->id,
        ])->assertSessionHasErrors('risk_assessment_id');
    }
});

test('the register shows the current revision of each assessment', function () {
    $ra = assessment();
    hazard($ra);
    $ra->approve(User::factory()->create());
    $ra->revise();

    $this->actingAs($this->userWithRole('auditor'))->get(route('risk-assessments.index'))
        ->assertInertia(fn (Assert $page) => $page->component('risk-assessments/index')
            ->has('assessments.data', 1)
            ->where('assessments.data.0.revision', 2));
});

test('supervisors cannot approve and workers cannot open assessments', function () {
    $ra = assessment();

    $this->actingAs($this->userWithRole('supervisor'))->put(route('risk-assessments.approve', $ra))->assertForbidden();
    $this->actingAs($this->userWithRole('worker'))->get(route('risk-assessments.index'))->assertForbidden();
});
