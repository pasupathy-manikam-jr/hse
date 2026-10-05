<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\Permit;
use App\Models\PermitIsolation;
use App\Models\RiskAssessment;
use App\Models\Site;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PermitController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $query = Permit::query()->visibleTo($user)
            ->with('site:id,code', 'area:id,name', 'contractor:id,name', 'creator:id,name')
            ->withCount('workers')
            ->when(in_array($request->input('type'), Permit::TYPES, true), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($request->filled('site_id'), fn (Builder $q) => $q->where('site_id', $request->integer('site_id')));
        $counts = TableQuery::tabs($query, 'status', Permit::STATUSES);

        return Inertia::render('permits/index', [
            'permits' => TableQuery::paginate(
                $query->when(in_array($request->input('status'), Permit::STATUSES, true), fn (Builder $q) => $q->where('status', $request->input('status'))),
                $request, ['number', 'description'], ['number', 'valid_from', 'created_at'], 'valid_from',
            ),
            'counts' => $counts,
            'sites' => Site::query()->availableTo($user)->get(['id', 'code as name']),
            'filters' => TableQuery::filters($request, ['status', 'type', 'site_id']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('permits/create', $this->formOptions($this->user($request)));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $permit = Permit::create(Arr::except($data, 'worker_ids'));
        $permit->workers()->sync($data['worker_ids']);
        $this->toast('success', __(':number requested. Confirm the precautions; then it can be approved.', ['number' => $permit->number]));

        return to_route('permits.show', $permit);
    }

    public function show(Request $request, Permit $permit): Response
    {
        $user = $this->user($request);
        abort_unless($permit->isVisibleTo($user), 404);

        $permit->load('site:id,code,name', 'area:id,name', 'contractor:id,name,approved,insurance_expires_on', 'riskAssessment:id,number,revision,title,status',
            'workers:id,name', 'creator:id,name', 'approver:id,name', 'gasTests.tester:id,name',
            'isolations.isolator:id,name', 'isolations.remover:id,name', 'signatures');

        return Inertia::render('permits/show', [
            'permit' => $permit,
            'precautions' => Permit::precautionsFor($permit->type),
            'problems' => $permit->status === 'requested' ? $permit->approvalProblems() : [],
            'conflicts' => $permit->isOpen() ? $permit->conflicts() : [],
            'gasLimits' => ['oxygen' => Permit::OXYGEN_RANGE, ...Permit::GAS_BELOW],
            ...$permit->status === 'requested' ? $this->formOptions($user, $permit->site_id) : [],
        ]);
    }

    /**
     * Change a permit before approval (details, workers).
     */
    public function update(Request $request, Permit $permit): RedirectResponse
    {
        $this->requested($request, $permit);
        $data = $this->validated($request, $permit);
        $permit->update(Arr::except($data, ['worker_ids', 'site_id']));
        $permit->workers()->sync($data['worker_ids']);

        return $this->done(__('Permit updated.'));
    }

    public function precautions(Request $request, Permit $permit): RedirectResponse
    {
        $this->requested($request, $permit);
        $keys = array_keys(Permit::precautionsFor($permit->type));
        $checked = $request->validate(['checked' => ['array'], 'checked.*' => [Rule::in($keys)]])['checked'] ?? [];

        $permit->update(['precautions' => array_fill_keys(array_values(array_intersect($keys, $checked)), true)]);

        return $this->done(__('Precautions saved.'));
    }

    public function storeGasTest(Request $request, Permit $permit): RedirectResponse
    {
        $this->open($request, $permit);

        if ($permit->type !== 'confined-space') {
            throw ValidationException::withMessages(['oxygen' => __('Gas tests are for confined-space permits.')]);
        }

        $reading = ['required', 'numeric', 'min:0', 'max:1000'];
        $test = $permit->gasTests()->create([
            ...$request->validate(['oxygen' => ['required', 'numeric', 'between:0,100'], 'lel' => $reading, 'h2s' => $reading, 'co' => $reading]),
            'tested_by' => $this->user($request)->id,
            'tested_at' => now(),
        ]);

        return $test->passed
            ? $this->done(__('Gas test passed.'))
            : $this->toast('error', __('Gas test FAILED: do not enter. Ventilate and test again.'));
    }

    public function storeIsolation(Request $request, Permit $permit): RedirectResponse
    {
        $this->open($request, $permit);
        $permit->isolations()->create([
            ...$request->validate([
                'point' => ['required', 'string', 'max:255'],
                'method' => ['required', 'string', 'max:255'],
                'lock_no' => ['nullable', 'string', 'max:50'],
            ]),
            'isolated_by' => $this->user($request)->id,
            'isolated_at' => now(),
        ]);

        return $this->done(__('Isolation recorded.'));
    }

    public function removeIsolation(Request $request, Permit $permit, PermitIsolation $isolation): RedirectResponse
    {
        $this->open($request, $permit);
        abort_unless($isolation->permit_id === $permit->id && $isolation->removed_at === null, 404);
        $isolation->update(['removed_by' => $this->user($request)->id, 'removed_at' => now()]);

        return $this->done(__('Isolation removed.'));
    }

    /**
     * Approve (signed, permit-issuer), start or resume, suspend, close (signed) or cancel.
     */
    public function transition(Request $request, Permit $permit): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($permit->isVisibleTo($user), 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(Permit::STATUSES)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'string'],
        ]);

        abort_if($data['status'] === 'approved' && ! $user->can('approve-permits'), 403);

        $permit->transitionTo($data['status'], $user, $data['reason'] ?? null, $data['password'] ?? null);

        return $this->done(__('Permit :status.', ['status' => $data['status']]));
    }

    public function destroy(Request $request, Permit $permit): RedirectResponse
    {
        $this->requested($request, $permit);
        $permit->delete();

        return to_route('permits.index');
    }

    /**
     * Choices for the request form: sites, approved assessments, contractors and people.
     *
     * @return array<string, mixed>
     */
    private function formOptions(User $user, ?int $siteId = null): array
    {
        $sites = Site::query()->availableTo($user)->when($siteId, fn (Builder $q) => $q->whereKey($siteId))
            ->with('areas:id,site_id,name')->get(['id', 'code', 'name']);

        return [
            'sites' => $sites,
            'types' => Permit::TYPES,
            'maxHours' => Permit::MAX_HOURS,
            'assessments' => RiskAssessment::query()->whereIn('site_id', $sites->pluck('id'))->where('status', 'approved')
                ->orderBy('number')->get(['id', 'site_id', 'number', 'title']),
            'contractors' => Contractor::query()->orderBy('name')->get(['id', 'name', 'approved', 'insurance_expires_on']),
            'people' => User::query()->where(fn (Builder $q) => $q->whereNull('site_id')->orWhereIn('site_id', $sites->pluck('id')))
                ->orderBy('name')->get(['id', 'name', 'site_id', 'contractor_id']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Permit $permit = null): array
    {
        $user = $this->user($request);
        $siteId = $permit->site_id ?? $request->integer('site_id');
        $data = $request->validate([
            'type' => ['required', Rule::in(Permit::TYPES)],
            'site_id' => [$permit ? 'prohibited' : 'required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('site_id', $siteId)],
            'risk_assessment_id' => ['required', Rule::exists('risk_assessments', 'id')->where('site_id', $siteId)->where('status', 'approved')],
            'contractor_id' => ['nullable', Rule::exists('contractors', 'id')],
            'description' => ['required', 'string', 'max:5000'],
            'valid_from' => ['required', 'date', 'after_or_equal:'.now()->subHour()->toIso8601String()],
            'valid_to' => ['required', 'date', 'after:valid_from'],
            'worker_ids' => ['required', 'array', 'min:1'],
            'worker_ids.*' => ['integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);

        if (Carbon::parse($data['valid_from'])->diffInMinutes(Carbon::parse($data['valid_to'])) > Permit::MAX_HOURS * 60) {
            throw ValidationException::withMessages(['valid_to' => __('A permit covers one shift: at most :h hours.', ['h' => Permit::MAX_HOURS])]);
        }

        return $data;
    }

    private function requested(Request $request, Permit $permit): void
    {
        abort_unless($permit->isVisibleTo($this->user($request)), 404);

        if ($permit->status !== 'requested') {
            throw ValidationException::withMessages(['status' => __('Only a requested permit can be changed.')]);
        }
    }

    private function open(Request $request, Permit $permit): void
    {
        abort_unless($permit->isVisibleTo($this->user($request)), 404);

        if (! $permit->isOpen()) {
            throw ValidationException::withMessages(['status' => __('This permit is :status.', ['status' => $permit->status])]);
        }
    }
}
