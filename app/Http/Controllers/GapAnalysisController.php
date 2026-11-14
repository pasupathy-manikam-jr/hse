<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\GapAnalysis;
use App\Models\GapAnalysisItem;
use App\Models\Site;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ISO 45001 gap analyses: rate each clause, raise actions for the gaps, compare scores over time.
 */
class GapAnalysisController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $query = GapAnalysis::query()->visibleTo($user)->with('site:id,code', 'creator:id,name')
            ->withCount(['items as gaps_count' => fn (Builder $q) => $q->where('rating', 'gap')])
            ->when($request->filled('site_id'), fn (Builder $q) => $q->where('site_id', $request->integer('site_id')));
        $counts = TableQuery::tabs($query, 'status', GapAnalysis::STATUSES);
        $page = TableQuery::paginate(
            $query->when(in_array($request->input('status'), GapAnalysis::STATUSES, true), fn (Builder $q) => $q->where('status', $request->input('status'))),
            $request, ['number', 'title'], ['number', 'assessed_on'], 'assessed_on',
        );
        // ponytail: one scores() query per row (a page of ~15); store the score on save if lists grow.
        foreach ($page->items() as $analysis) {
            $analysis->setAttribute('score', $analysis->scores()['overall']);
        }

        return Inertia::render('gap-analyses/index', [
            'analyses' => $page,
            'counts' => $counts,
            'sites' => Site::query()->availableTo($user)->get(['id', 'code', 'name']),
            'filters' => TableQuery::filters($request, ['status', 'site_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $analysis = GapAnalysis::create($request->validate([
            'site_id' => ['required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'title' => ['required', 'string', 'max:255'],
            'assessed_on' => ['required', 'date'],
        ]));

        return to_route('gap-analyses.show', $analysis);
    }

    public function show(Request $request, GapAnalysis $gapAnalysis): Response
    {
        abort_unless($gapAnalysis->isVisibleTo($this->user($request)), 404);
        $previous = GapAnalysis::query()->where('site_id', $gapAnalysis->site_id)->where('status', 'completed')
            ->whereKeyNot($gapAnalysis->id)->whereDate('assessed_on', '<=', $gapAnalysis->assessed_on)
            ->orderByDesc('assessed_on')->orderByDesc('id')->first();

        return Inertia::render('gap-analyses/show', [
            'analysis' => $gapAnalysis->load('site:id,code,name', 'creator:id,name', 'items.clause:id,number,title', 'actions.owner:id,name', 'actions.verifier:id,name'),
            'scores' => $gapAnalysis->scores(),
            'previous' => $previous ? ['number' => $previous->number, 'assessed_on' => $previous->assessed_on->toDateString(), ...$previous->scores()] : null,
            'users' => User::query()->where(fn (Builder $q) => $q->whereNull('site_id')->orWhere('site_id', $gapAnalysis->site_id))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Save the ratings and evidence (all clauses at once; progress can be saved part-way).
     */
    public function rate(Request $request, GapAnalysis $gapAnalysis): RedirectResponse
    {
        abort_unless($gapAnalysis->isVisibleTo($this->user($request)), 404);
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.rating' => ['nullable', Rule::in(GapAnalysisItem::RATINGS)],
            'items.*.evidence' => ['nullable', 'string', 'max:5000'],
        ]);

        $ratings = [];

        foreach ($data['items'] as $item) {
            $ratings[(int) $item['id']] = ['rating' => $item['rating'] ?? null, 'evidence' => $item['evidence'] ?? null];
        }

        $gapAnalysis->rate($ratings);

        return $this->done(__('Ratings saved.'));
    }

    public function complete(Request $request, GapAnalysis $gapAnalysis): RedirectResponse
    {
        abort_unless($gapAnalysis->isVisibleTo($this->user($request)), 404);
        $gapAnalysis->complete($request->validate(['summary' => ['required', 'string', 'max:10000']])['summary']);

        return $this->done(__('Gap analysis completed.'));
    }

    public function storeAction(Request $request, GapAnalysis $gapAnalysis): RedirectResponse
    {
        abort_unless($gapAnalysis->isVisibleTo($this->user($request)), 404);

        $gapAnalysis->raiseAction($request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'control_level' => ['required', Rule::in(Action::CONTROL_LEVELS)],
            'priority' => ['required', Rule::in(Action::PRIORITIES)],
            'owner_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]));

        return $this->done(__('Action raised.'));
    }
}
