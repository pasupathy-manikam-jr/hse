<?php

namespace App\Http\Controllers;

use App\Actions\Incidents\DraftIncident;
use App\Models\Action;
use App\Models\Area;
use App\Models\Incident;
use App\Models\IncidentPerson;
use App\Models\Observation;
use App\Models\RiskAssessment;
use App\Models\Site;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class IncidentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $query = Incident::query()->visibleTo($user)
            ->with('site:id,code', 'area:id,name')
            ->withCount('people')
            ->when(in_array($request->input('type'), Incident::TYPES, true), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when(in_array($request->input('classification'), Incident::CLASSIFICATIONS, true), fn (Builder $q) => $q->where('classification', $request->input('classification')))
            ->when($request->filled('site_id'), fn (Builder $q) => $q->where('site_id', $request->integer('site_id')));
        $counts = TableQuery::tabs($query, 'status', Incident::STATUSES);

        return Inertia::render('incidents/index', [
            'incidents' => TableQuery::paginate(
                $query->when(in_array($request->input('status'), Incident::STATUSES, true), fn (Builder $q) => $q->where('status', $request->input('status'))),
                $request, ['number', 'title'], ['number', 'occurred_at', 'created_at'], 'occurred_at',
            ),
            'counts' => $counts,
            'sites' => Site::query()->availableTo($user)->get(['id', 'code as name']),
            'filters' => TableQuery::filters($request, ['status', 'type', 'classification', 'site_id']),
        ]);
    }

    /**
     * The report form, pre-filled when escalating an observation (?observation=id).
     */
    public function create(Request $request): Response
    {
        $user = $this->user($request);
        $observation = $request->filled('observation')
            ? Observation::query()->visibleTo($user)->doesntHave('incident')->find($request->integer('observation'))
            : null;

        return Inertia::render('incidents/create', [
            'sites' => Site::query()->availableTo($user)->with('areas:id,site_id,name')->get(['id', 'code', 'name']),
            'defaultSiteId' => $user->site_id,
            'observation' => $observation?->only(['id', 'number', 'site_id', 'area_id', 'description', 'immediate_action', 'observed_at']),
            'aiDrafting' => DraftIncident::isEnabled(),
        ]);
    }

    /**
     * Draft the report fields from a plain-words account (JSON for the form; nothing is saved).
     */
    public function draft(Request $request, DraftIncident $drafter): JsonResponse
    {
        abort_unless(DraftIncident::isEnabled(), 404);
        $user = $this->user($request);
        $data = $request->validate([
            'account' => ['required', 'string', 'min:20', 'max:5000'],
            'site_id' => ['nullable', Rule::exists('sites', 'id')->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
        ]);

        $areas = isset($data['site_id']) ? Area::query()->where('site_id', $request->integer('site_id'))->orderBy('name')->pluck('name')->all() : [];

        try {
            return response()->json(['draft' => $drafter->draft($data['account'], array_values(array_map('strval', $areas)))]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['account' => $e->getMessage()]);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            ...$this->detailRules($request, $user),
            'observation_id' => ['nullable', Rule::exists('observations', 'id'), Rule::unique('incidents', 'observation_id')],
            ...Incident::photoRules(),
        ]);

        if ($request->filled('observation_id') && ! Observation::query()->findOrFail($request->integer('observation_id'))->isVisibleTo($user)) {
            throw ValidationException::withMessages(['observation_id' => __('That observation is outside your site.')]);
        }

        $incident = Incident::create(Arr::except($data, 'photos'));
        $incident->attachPhotos($request->file('photos', []));
        $this->toast('success', __(':number reported. Add the people involved.', ['number' => $incident->number]));

        return to_route('incidents.show', $incident);
    }

    public function show(Request $request, Incident $incident): Response
    {
        $user = $this->user($request);
        abort_unless($incident->isVisibleTo($user), 404);

        $incident->load('site:id,code,name', 'site.areas:id,site_id,name', 'area:id,name', 'creator:id,name', 'closer:id,name',
            'observation:id,number', 'riskAssessment:id,number,revision,title', 'people', 'photos:id,photoable_type,photoable_id', 'actions.owner:id,name', 'actions.verifier:id,name');

        return Inertia::render('incidents/show', [
            'incident' => [...$incident->toArray(), 'investigation_complete' => $incident->investigationComplete()],
            // Approved assessments at this site, to link the one that should have controlled the incident.
            'assessments' => RiskAssessment::query()->where('site_id', $incident->site_id)->where('status', 'approved')
                ->orderBy('number')->get(['id', 'number', 'title']),
            // People who work at this site (or across all sites): action owners and staff involved.
            'users' => User::query()->where(fn (Builder $q) => $q->whereNull('site_id')->orWhere('site_id', $incident->site_id))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        $this->editable($request, $incident);
        $incident->update(Arr::except($request->validate($this->detailRules($request, $this->user($request), $incident)), 'site_id'));

        return $this->done(__('Incident updated.'));
    }

    public function storePerson(Request $request, Incident $incident): RedirectResponse
    {
        $this->editable($request, $incident);
        $incident->people()->create($this->personData($request, $incident));

        return $this->done(__('Person added.'));
    }

    public function updatePerson(Request $request, Incident $incident, IncidentPerson $person): RedirectResponse
    {
        $this->editable($request, $incident);
        abort_unless($person->incident_id === $incident->id, 404);
        $person->update($this->personData($request, $incident));

        return $this->done(__('Person updated.'));
    }

    public function destroyPerson(Request $request, Incident $incident, IncidentPerson $person): RedirectResponse
    {
        $this->editable($request, $incident);
        abort_unless($person->incident_id === $incident->id, 404);
        $person->delete();

        return $this->done(__('Person removed.'));
    }

    /**
     * Save the investigation; the first save starts it (reported → under investigation).
     */
    public function investigate(Request $request, Incident $incident): RedirectResponse
    {
        $this->editable($request, $incident);
        $data = $request->validate([
            'investigation_team' => ['nullable', 'string', 'max:2000'],
            'sequence_of_events' => ['nullable', 'string', 'max:10000'],
            'whys' => ['nullable', 'array', 'max:5'],
            'whys.*' => ['nullable', 'string', 'max:1000'],
            'root_cause_category' => ['nullable', Rule::in(Incident::ROOT_CAUSE_CATEGORIES)],
            'root_cause' => ['nullable', 'string', 'max:5000'],
            'contributing_factors' => ['nullable', 'string', 'max:5000'],
        ]);

        $incident->update([...$data, 'whys' => array_values(array_filter($data['whys'] ?? [], 'filled'))]);

        if ($incident->status === 'reported') {
            $incident->transitionTo('under-investigation');
        }

        return $this->done(__('Investigation saved.'));
    }

    public function transition(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless($incident->isVisibleTo($this->user($request)), 404);
        $incident->transitionTo($request->validate(['status' => ['required', Rule::in(Incident::STATUSES)]])['status']);

        return $this->done(__('Incident moved to :status.', ['status' => str_replace('-', ' ', $incident->status)]));
    }

    public function storeAction(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless($incident->isVisibleTo($this->user($request)), 404);

        $incident->raiseAction($request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'control_level' => ['required', Rule::in(Action::CONTROL_LEVELS)],
            'priority' => ['required', Rule::in(Action::PRIORITIES)],
            'owner_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]));

        return $this->done(__('Action raised.'));
    }

    public function destroy(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless($incident->isVisibleTo($this->user($request)), 404);

        if ($incident->actions()->exists()) {
            return $this->toast('error', __('This incident has actions. Close it instead.'));
        }

        $incident->delete();

        return to_route('incidents.index');
    }

    /**
     * The OSHA 300 log and 300A summary for one site and year: one line per recordable injured person.
     */
    public function log(Request $request): Response
    {
        $user = $this->user($request);
        $sites = Site::query()->availableTo($user)->get(['id', 'code', 'name']);
        $site = $sites->firstWhere('id', $request->integer('site_id')) ?? $sites->first();
        $year = $request->integer('year') ?: now()->year;

        $cases = IncidentPerson::query()
            ->with('incident:id,number,site_id,area_id,type,title,occurred_at', 'incident.area:id,name')
            ->where('role', 'injured')->whereIn('treatment', Incident::RECORDABLE)
            ->whereHas('incident', fn (Builder $q) => $q->where('site_id', $site?->id)->whereYear('occurred_at', $year))
            ->get()->sortBy('incident.occurred_at')->values()
            ->map(fn (IncidentPerson $p) => [
                'case' => $p->incident->number.'-'.$p->id,
                'incident_id' => $p->incident_id,
                // 1904.29(b)(7): a privacy case is logged without the person's name.
                'name' => $p->privacy_case ? __('Privacy case') : $p->name,
                'job_title' => $p->job_title,
                'date' => $p->incident->occurred_at->toDateString(),
                'where' => $p->incident->area->name ?? '',
                'description' => collect([$p->injury_nature, $p->body_part, $p->incident->title])->filter()->implode(' — '),
                'outcome' => $p->treatment,
                // OSHA 1904.7(b)(3)(vii): stop counting at 180 days.
                'days_away' => min($p->days_lost, 180),
                'days_restricted' => min($p->days_restricted, 180),
                'category' => $p->incident->type === 'illness' ? ($p->illness_type ?? 'other-illness') : 'injury',
            ]);

        return Inertia::render('incidents/log', [
            'sites' => $sites,
            'site' => $site,
            'year' => $year,
            'cases' => $cases,
            'totals' => [
                'deaths' => $cases->where('outcome', 'fatality')->count(),
                'days_away_cases' => $cases->where('outcome', 'lost-time')->count(),
                'restricted_cases' => $cases->where('outcome', 'restricted')->count(),
                'other_cases' => $cases->where('outcome', 'medical')->count(),
                'days_away' => $cases->sum('days_away'),
                'days_restricted' => $cases->sum('days_restricted'),
                'injuries' => $cases->where('category', 'injury')->count(),
                ...collect(IncidentPerson::ILLNESS_TYPES)->mapWithKeys(fn (string $type) => [$type => $cases->where('category', $type)->count()])->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRules(Request $request, User $user, ?Incident $incident = null): array
    {
        $siteId = $incident->site_id ?? $request->integer('site_id');

        return [
            'site_id' => [$incident ? 'nullable' : 'required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('site_id', $siteId)],
            'type' => ['required', Rule::in(Incident::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'immediate_actions' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'risk_assessment_id' => ['nullable', Rule::exists('risk_assessments', 'id')->where('site_id', $siteId)->where('status', 'approved')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personData(Request $request, Incident $incident): array
    {
        $injured = $request->input('role') === 'injured';
        $illness = $injured && $incident->type === 'illness';
        $data = $request->validate([
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'name' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(IncidentPerson::ROLES)],
            'treatment' => [$injured ? 'required' : 'prohibited', 'nullable', Rule::in(IncidentPerson::TREATMENTS)],
            'body_part' => ['nullable', 'string', 'max:255'],
            'injury_nature' => ['nullable', 'string', 'max:255'],
            'days_lost' => ['nullable', 'integer', 'min:0', 'max:365', Rule::requiredIf($injured && $request->input('treatment') === 'lost-time')],
            'days_restricted' => ['nullable', 'integer', 'min:0', 'max:365'],
            'illness_type' => [$illness ? 'required' : 'prohibited', 'nullable', Rule::in(IncidentPerson::ILLNESS_TYPES)],
            'privacy_case' => ['boolean'],
        ]);

        return [
            ...$data,
            // A linked user's name is copied, so the record keeps it if the account changes.
            'name' => $request->filled('user_id') ? User::query()->findOrFail($request->integer('user_id'))->name : $data['name'],
            'days_lost' => $injured ? (int) ($data['days_lost'] ?? 0) : 0,
            'days_restricted' => $injured ? (int) ($data['days_restricted'] ?? 0) : 0,
            'illness_type' => $illness ? $data['illness_type'] : null,
            'privacy_case' => $injured && $request->boolean('privacy_case'),
        ];
    }

    private function editable(Request $request, Incident $incident): void
    {
        abort_unless($incident->isVisibleTo($this->user($request)), 404);

        if ($incident->status === 'closed') {
            throw ValidationException::withMessages(['status' => __('This incident is closed.')]);
        }
    }
}
