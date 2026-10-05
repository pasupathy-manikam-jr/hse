<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\AuditFinding;
use App\Models\InternalAudit;
use App\Models\IsoClause;
use App\Models\Site;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Internal OH&S audits against ISO 45001 (§9.2).
 */
class InternalAuditController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $query = InternalAudit::query()->visibleTo($user)->with('site:id,code', 'leadAuditor:id,name')
            ->withCount(['findings', 'findings as nonconformities_count' => fn (Builder $q) => $q->where('type', 'like', '%nonconformity')]);
        $counts = TableQuery::tabs($query, 'status', InternalAudit::STATUSES);

        return Inertia::render('audits/index', [
            'audits' => TableQuery::paginate(
                $query->when(in_array($request->input('status'), InternalAudit::STATUSES, true), fn (Builder $q) => $q->where('status', $request->input('status'))),
                $request, ['number', 'title'], ['number', 'planned_on'], 'planned_on',
            ),
            'counts' => $counts,
            'sites' => Site::query()->availableTo($user)->get(['id', 'code', 'name']),
            'people' => User::query()->orderBy('name')->get(['id', 'name']),
            'clauses' => IsoClause::query()->orderBy('id')->get(['id', 'number', 'title']),
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'site_id' => ['required', Rule::exists('sites', 'id')->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'lead_auditor_id' => ['required', Rule::exists('users', 'id')],
            'planned_on' => ['required', 'date'],
            'clause_ids' => ['required', 'array', 'min:1'],
            'clause_ids.*' => ['integer', Rule::exists('iso_clauses', 'id')],
        ]);

        $audit = InternalAudit::create(Arr::except($data, 'clause_ids'));
        $audit->clauses()->sync($data['clause_ids']);

        return to_route('audits.show', $audit);
    }

    public function show(Request $request, InternalAudit $audit): Response
    {
        abort_unless($audit->isVisibleTo($this->user($request)), 404);

        return Inertia::render('audits/show', [
            'audit' => $audit->load('site:id,code,name', 'leadAuditor:id,name', 'clauses:id,number,title',
                'findings.clause:id,number', 'findings.action:id,number,status', 'actions.owner:id,name', 'actions.verifier:id,name'),
            'users' => User::query()->where(fn (Builder $q) => $q->whereNull('site_id')->orWhere('site_id', $audit->site_id))->orderBy('name')->get(['id', 'name']),
            'findingTypes' => AuditFinding::TYPES,
        ]);
    }

    public function start(Request $request, InternalAudit $audit): RedirectResponse
    {
        abort_unless($audit->isVisibleTo($this->user($request)), 404);
        $audit->start();

        return $this->done(__('Audit started.'));
    }

    public function storeFinding(Request $request, InternalAudit $audit): RedirectResponse
    {
        abort_unless($audit->isVisibleTo($this->user($request)), 404);
        $nonconformity = str_ends_with((string) $request->input('type'), 'nonconformity');
        $data = $request->validate([
            'type' => ['required', Rule::in(AuditFinding::TYPES)],
            'iso_clause_id' => ['nullable', Rule::exists('iso_clauses', 'id')],
            'description' => ['required', 'string', 'max:5000'],
            'owner_id' => [$nonconformity ? 'required' : 'prohibited', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'due_on' => [$nonconformity ? 'required' : 'prohibited', 'date', 'after_or_equal:today'],
        ]);

        $audit->addFinding(
            ['type' => $data['type'], 'iso_clause_id' => isset($data['iso_clause_id']) ? $request->integer('iso_clause_id') : null, 'description' => $data['description']],
            $nonconformity ? ['owner_id' => $request->integer('owner_id'), 'due_on' => $data['due_on']] : null,
        );

        return $this->done($nonconformity ? __('Nonconformity recorded and an action raised.') : __('Finding recorded.'));
    }

    public function complete(Request $request, InternalAudit $audit): RedirectResponse
    {
        abort_unless($audit->isVisibleTo($this->user($request)), 404);
        $audit->update($request->validate(['summary' => ['required', 'string', 'max:10000']]));
        $audit->complete();

        return $this->done(__('Audit completed.'));
    }

    public function storeAction(Request $request, InternalAudit $audit): RedirectResponse
    {
        abort_unless($audit->isVisibleTo($this->user($request)), 404);

        $audit->raiseAction($request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'control_level' => ['required', Rule::in(Action::CONTROL_LEVELS)],
            'priority' => ['required', Rule::in(Action::PRIORITIES)],
            'owner_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]));

        return $this->done(__('Action raised.'));
    }
}
