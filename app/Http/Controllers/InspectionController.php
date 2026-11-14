<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\ChecklistTemplate;
use App\Models\Inspection;
use App\Models\Site;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InspectionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $query = Inspection::query()->visibleTo($user)
            ->with('site:id,code', 'area:id,name', 'creator:id,name')
            ->withCount(['answers as failed_count' => fn (Builder $q) => $q->where('passed', false)])
            ->when($request->filled('site_id'), fn (Builder $q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('template_id'), fn (Builder $q) => $q->where('checklist_template_id', $request->integer('template_id')));
        $counts = TableQuery::tabs($query, 'status', Inspection::STATUSES);

        return Inertia::render('inspections/index', [
            'inspections' => TableQuery::paginate(
                $query->when(in_array($request->input('status'), Inspection::STATUSES, true), fn (Builder $q) => $q->where('status', $request->input('status'))),
                $request, ['number', 'template_name'], ['number', 'created_at', 'score'], 'created_at',
            ),
            'counts' => $counts,
            'templates' => ChecklistTemplate::query()->where('active', true)->has('items')->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->availableTo($user)->with('areas:id,site_id,name')->get(['id', 'code', 'name']),
            'filters' => TableQuery::filters($request, ['status', 'site_id', 'template_id']),
        ]);
    }

    /**
     * Start an inspection from an active template.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'checklist_template_id' => ['required', Rule::exists('checklist_templates', 'id')->where('active', true)],
            'site_id' => ['required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('site_id', $request->integer('site_id'))],
        ]);

        $template = ChecklistTemplate::query()->with('items')->findOrFail($request->integer('checklist_template_id'));
        $inspection = Inspection::start($template, $request->integer('site_id'), isset($data['area_id']) ? $request->integer('area_id') : null);

        return to_route('inspections.show', $inspection);
    }

    public function show(Request $request, Inspection $inspection): Response
    {
        $user = $this->user($request);
        abort_unless($inspection->isVisibleTo($user), 404);

        $inspection->load('site:id,code,name', 'area:id,name', 'creator:id,name', 'answers.photos:id,photoable_type,photoable_id',
            'actions.owner:id,name', 'actions.verifier:id,name');

        return Inertia::render('inspections/show', [
            'inspection' => $inspection,
            'users' => User::query()->where(fn (Builder $q) => $q->whereNull('site_id')->orWhere('site_id', $inspection->site_id))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Save the answers (with any photos), and complete the inspection when asked.
     * Only the inspector who started it fills it in.
     */
    public function saveAnswers(Request $request, Inspection $inspection): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($inspection->isVisibleTo($user), 404);

        $data = $request->validate(Inspection::answerRules());
        $inspection->saveAnswers($user, $data['answers'] ?? [], $data['notes'] ?? null, $request->file('answers') ? collect($request->file('answers'))->map(fn ($a) => $a['photos'] ?? [])->all() : []);

        if ($request->boolean('complete')) {
            $inspection->complete($user);

            return $this->done(__('Inspection completed: :score%.', ['score' => $inspection->score ?? '—']));
        }

        return $this->done(__('Answers saved.'));
    }

    public function storeAction(Request $request, Inspection $inspection): RedirectResponse
    {
        abort_unless($inspection->isVisibleTo($this->user($request)), 404);

        $inspection->raiseAction($request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'control_level' => ['required', Rule::in(Action::CONTROL_LEVELS)],
            'priority' => ['required', Rule::in(Action::PRIORITIES)],
            'owner_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]));

        return $this->done(__('Action raised.'));
    }

    /**
     * Discard an inspection that was started by mistake (completed ones are the record).
     */
    public function destroy(Request $request, Inspection $inspection): RedirectResponse
    {
        abort_unless($inspection->isVisibleTo($this->user($request)), 404);

        if ($inspection->status !== 'in-progress') {
            return $this->toast('error', __('A completed inspection cannot be deleted.'));
        }

        $inspection->answers->each->delete();
        $inspection->delete();

        return to_route('inspections.index');
    }
}
