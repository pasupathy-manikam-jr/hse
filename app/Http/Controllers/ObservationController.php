<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\Observation;
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

class ObservationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $query = Observation::query()->visibleTo($user)
            ->with('site:id,code', 'area:id,name', 'reporter:id,name')
            ->withCount('photos')
            ->when(in_array($request->input('type'), Observation::TYPES, true), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when(in_array($request->input('potential'), Observation::POTENTIALS, true), fn (Builder $q) => $q->where('potential', $request->input('potential')))
            ->when($request->filled('site_id'), fn (Builder $q) => $q->where('site_id', $request->integer('site_id')));
        $counts = TableQuery::tabs($query, 'status', Observation::STATUSES);

        return Inertia::render('observations/index', [
            'observations' => TableQuery::paginate(
                $query->when(in_array($request->input('status'), Observation::STATUSES, true), fn (Builder $q) => $q->where('status', $request->input('status'))),
                $request, ['number', 'description'], ['number', 'observed_at', 'created_at'], 'observed_at',
            ),
            'counts' => $counts,
            'sites' => Site::query()->availableTo($user)->get(['id', 'code as name']),
            'filters' => TableQuery::filters($request, ['status', 'type', 'potential', 'site_id']),
        ]);
    }

    /**
     * The mobile report form.
     */
    public function create(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('observations/create', [
            'sites' => Site::query()->availableTo($user)->with('areas:id,site_id,name')->get(['id', 'code', 'name']),
            'defaultSiteId' => $user->site_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        // A queued report sent again after a lost reply: already stored, so just confirm it.
        if ($request->filled('client_ref') && ($existing = Observation::query()->where('client_ref', $request->string('client_ref')->toString())->first())) {
            $this->toast('success', __(':number reported. Thank you.', ['number' => $existing->number]));

            return to_route('observations.create');
        }

        $data = $request->validate([
            'client_ref' => ['nullable', 'uuid'],
            'site_id' => ['required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('site_id', $request->integer('site_id'))],
            'type' => ['required', Rule::in(Observation::TYPES)],
            'potential' => ['required', Rule::in(Observation::POTENTIALS)],
            'description' => ['required', 'string', 'max:5000'],
            'immediate_action' => ['nullable', 'string', 'max:5000'],
            'observed_at' => ['required', 'date', 'before_or_equal:now'],
            'anonymous' => ['boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            ...Observation::photoRules(),
        ]);

        $anonymous = $request->boolean('anonymous');
        $observation = Observation::create([
            ...Arr::except($data, 'photos'),
            'anonymous' => $anonymous,
            'reporter_id' => $anonymous ? null : $user->id,
        ]);
        $observation->attachPhotos($request->file('photos', []));

        $this->toast('success', __(':number reported. Thank you.', ['number' => $observation->number]));

        return $user->can('manage-observations')
            ? to_route('observations.show', $observation)
            : to_route('observations.create');
    }

    public function show(Request $request, Observation $observation): Response
    {
        $user = $this->user($request);
        abort_unless($observation->isVisibleTo($user), 404);

        $observation->load('site:id,code,name', 'area:id,name', 'reporter:id,name', 'closer:id,name', 'photos:id,photoable_type,photoable_id', 'incident:id,observation_id,number',
            'actions.owner:id,name', 'actions.verifier:id,name');

        return Inertia::render('observations/show', [
            'observation' => $observation,
            // Anyone working at this site (or across all sites) can own an action.
            'owners' => User::query()->where(fn (Builder $q) => $q->whereNull('site_id')->orWhere('site_id', $observation->site_id))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function close(Request $request, Observation $observation): RedirectResponse
    {
        abort_unless($observation->isVisibleTo($this->user($request)), 404);
        $observation->transitionTo('closed');

        return $this->done(__('Observation closed.'));
    }

    public function storeAction(Request $request, Observation $observation): RedirectResponse
    {
        abort_unless($observation->isVisibleTo($this->user($request)), 404);

        $observation->raiseAction($request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'control_level' => ['required', Rule::in(Action::CONTROL_LEVELS)],
            'priority' => ['required', Rule::in(Action::PRIORITIES)],
            'owner_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]));

        return $this->done(__('Action raised.'));
    }

    public function destroy(Request $request, Observation $observation): RedirectResponse
    {
        abort_unless($observation->isVisibleTo($this->user($request)), 404);

        if ($observation->actions()->exists()) {
            return $this->toast('error', __('This observation has actions. Close it instead.'));
        }

        $observation->delete();

        return to_route('observations.index');
    }
}
