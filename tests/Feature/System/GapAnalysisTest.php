<?php

use App\Models\GapAnalysis;
use App\Models\GapAnalysisItem;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\IsoClauseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(IsoClauseSeeder::class);
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
});

/**
 * Every item of the analysis with this rating (and evidence), as the ratings form sends them.
 *
 * @return list<array{id: int, rating: string, evidence: string|null}>
 */
function rateAll(GapAnalysis $analysis, string $rating, ?string $evidence = null): array
{
    return $analysis->items->map(fn (GapAnalysisItem $i) => ['id' => $i->id, 'rating' => $rating, 'evidence' => $evidence])->all();
}

test('a gap analysis lists every ISO 45001 clause and scores conforms 1, partial ½, gap 0, leaving N/A out', function () {
    $this->actingAs($this->userWithRole('auditor'));
    $this->post(route('gap-analyses.store'), ['site_id' => $this->site->id, 'title' => 'Pre-certification', 'assessed_on' => today()->toDateString()])
        ->assertRedirect();

    $analysis = GapAnalysis::query()->sole();
    expect($analysis->number)->toStartWith('GAP-')->and($analysis->items)->toHaveCount(29);

    $items = $analysis->items()->with('clause')->get()->keyBy(fn (GapAnalysisItem $i) => $i->clause->number);
    $this->put(route('gap-analyses.rate', $analysis), ['items' => [
        ['id' => $items['4.1']->id, 'rating' => 'conforms'],
        ['id' => $items['4.2']->id, 'rating' => 'partial', 'evidence' => 'No interested-parties register.'],
        ['id' => $items['4.3']->id, 'rating' => 'gap', 'evidence' => 'Scope not written.'],
        ['id' => $items['4.4']->id, 'rating' => 'not-applicable'],
        ['id' => $items['5.1']->id, 'rating' => 'conforms'],
    ]])->assertSessionHasNoErrors();

    expect($analysis->scores())->toMatchArray(['overall' => 62.5])
        ->and($analysis->scores()['sections'])->toMatchArray(['4' => 50.0, '5' => 100.0, '6' => null]);

    $this->put(route('gap-analyses.rate', $analysis), ['items' => [['id' => $items['5.2']->id, 'rating' => 'maybe']]])->assertSessionHasErrors('items.0.rating');
});

test('completing needs every clause rated, each gap explained and a summary; then it is read-only', function () {
    $analysis = GapAnalysis::create(['site_id' => $this->site->id, 'title' => 'x', 'assessed_on' => today()]);
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->put(route('gap-analyses.complete', $analysis), ['summary' => 'Ready.'])->assertSessionHasErrors('status');

    $this->put(route('gap-analyses.rate', $analysis), ['items' => rateAll($analysis, 'gap')]);
    $this->put(route('gap-analyses.complete', $analysis), ['summary' => 'Ready.'])->assertSessionHasErrors('status');
    $this->put(route('gap-analyses.complete', $analysis), [])->assertSessionHasErrors('summary');

    $this->put(route('gap-analyses.rate', $analysis), ['items' => rateAll($analysis, 'gap', 'Missing.')]);
    $this->put(route('gap-analyses.complete', $analysis), ['summary' => 'Far from ready.'])->assertSessionHasNoErrors();

    expect($analysis->fresh()->status)->toBe('completed');
    $this->put(route('gap-analyses.rate', $analysis), ['items' => rateAll($analysis, 'conforms')])->assertSessionHasErrors('status');
});

test('actions are raised from a gap analysis, and the page compares with the last completed one', function () {
    $last = GapAnalysis::create(['site_id' => $this->site->id, 'title' => 'Last year', 'assessed_on' => today()->subYear()]);
    $last->rate(collect(rateAll($last, 'partial', 'x'))->keyBy('id')->map(fn ($i) => ['rating' => 'partial', 'evidence' => 'x'])->all());
    $last->complete('Baseline.');
    $analysis = GapAnalysis::create(['site_id' => $this->site->id, 'title' => 'This year', 'assessed_on' => today()]);
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->post(route('gap-analyses.actions.store', $analysis), [
        'description' => 'Write the OH&S scope (§4.3).', 'control_level' => 'administrative', 'priority' => 'medium',
        'owner_id' => User::factory()->create()->id, 'due_on' => today()->addMonth()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($analysis->actions()->sole()->site_id)->toBe($this->site->id);
    $this->get(route('gap-analyses.show', $analysis))->assertInertia(fn (Assert $page) => $page->component('gap-analyses/show')
        ->where('previous.number', $last->number)->where('previous.overall', 50));
});

test('workers cannot open gap analyses, supervisors cannot edit them, and other sites stay hidden', function () {
    $hidden = GapAnalysis::create(['site_id' => Site::create(['code' => 'SA', 'name' => 'Plant'])->id, 'title' => 'x', 'assessed_on' => today()]);
    $mine = GapAnalysis::create(['site_id' => $this->site->id, 'title' => 'y', 'assessed_on' => today()]);
    $supervisor = $this->userWithRole('supervisor');
    $supervisor->update(['site_id' => $this->site->id]);

    $this->actingAs($this->userWithRole('worker'))->get(route('gap-analyses.index'))->assertForbidden();
    $this->actingAs($supervisor)->get(route('gap-analyses.index'))->assertInertia(fn (Assert $page) => $page->has('analyses.data', 1));
    $this->get(route('gap-analyses.show', $hidden))->assertNotFound();
    $this->put(route('gap-analyses.rate', $mine), ['items' => rateAll($mine, 'gap')])->assertForbidden();
    $this->post(route('gap-analyses.store'), ['site_id' => $this->site->id, 'title' => 'z', 'assessed_on' => today()->toDateString()])->assertForbidden();
});
