<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permit;
use App\Models\PermitIsolation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Permit work in the field: gas tests, isolations, and moving a permit through its states.
 * Requesting a permit and confirming precautions stay on the web.
 */
class PermitController extends Controller
{
    /**
     * Permits at the person's sites. By default the open ones; `status` picks one state.
     *
     * @return LengthAwarePaginator<int, Permit>
     */
    public function index(Request $request): LengthAwarePaginator
    {
        $status = $request->input('status');

        return Permit::query()->visibleTo($this->user($request))
            ->when(in_array($status, Permit::STATUSES, true), fn ($q) => $q->where('status', $status), fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled']))
            ->with('site:id,code', 'area:id,name', 'contractor:id,name')->withCount('workers')
            ->orderBy('valid_from')->paginate(30);
    }

    /**
     * One permit with its workers, equipment, gas tests and isolations; `problems` lists what
     * still blocks approval, `conflicts` the clashing permits in the same area.
     *
     * @return array<string, mixed>
     */
    public function show(Request $request, Permit $permit): array
    {
        abort_unless($permit->isVisibleTo($this->user($request)), 404);

        return [
            'permit' => $permit->load('site:id,code,name', 'area:id,name', 'contractor:id,name', 'riskAssessment:id,number,title,status',
                'workers:id,name', 'equipment:id,asset_tag,name,status,next_inspection_on', 'gasTests.tester:id,name',
                'isolations.isolator:id,name', 'isolations.remover:id,name', 'creator:id,name', 'approver:id,name'),
            'precautions' => Permit::precautionsFor($permit->type),
            'problems' => $permit->status === 'requested' ? $permit->approvalProblems() : [],
            'conflicts' => $permit->isOpen() ? $permit->conflicts() : [],
            'gas_limits' => ['oxygen' => Permit::OXYGEN_RANGE, ...Permit::GAS_BELOW],
        ];
    }

    /** Record gas readings (confined space). `passed` false means do not enter. */
    public function gasTest(Request $request, Permit $permit): JsonResponse
    {
        abort_unless($permit->isVisibleTo($this->user($request)), 404);

        return response()->json($permit->recordGasTest($request->validate(Permit::gasTestRules()), $this->user($request)), 201);
    }

    /** Record a lockout point. */
    public function storeIsolation(Request $request, Permit $permit): JsonResponse
    {
        abort_unless($permit->isVisibleTo($this->user($request)), 404);

        return response()->json($permit->addIsolation($request->validate(Permit::isolationRules()), $this->user($request)), 201);
    }

    /** Take a lock off. A permit closes only when every isolation is removed. */
    public function removeIsolation(Request $request, Permit $permit, PermitIsolation $isolation): PermitIsolation
    {
        abort_unless($permit->isVisibleTo($this->user($request)), 404);
        $permit->removeIsolation($isolation, $this->user($request));

        return $isolation->refresh();
    }

    /**
     * Move the permit: `approved` (permit issuer, signed with `password`), `active` (start or
     * resume), `suspended` (with `reason`), `closed` (signed with `password`) or `cancelled`
     * (with `reason`). A refusal comes back as a 422 on `status` saying why.
     */
    public function transition(Request $request, Permit $permit): Permit
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

        return $permit->refresh();
    }
}
