<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Observation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reporting from the field: unsafe acts and conditions, near misses, good practice.
 */
class ObservationController extends Controller
{
    /**
     * Reports, newest first: every report at the person's sites for supervisors and managers,
     * otherwise only their own (named) reports.
     *
     * @return LengthAwarePaginator<int, Observation>
     */
    public function index(Request $request): LengthAwarePaginator
    {
        $user = $this->user($request);

        return Observation::query()
            ->when($user->can('manage-observations'), fn ($q) => $q->visibleTo($user), fn ($q) => $q->where('reporter_id', $user->id))
            ->with('site:id,code', 'area:id,name', 'reporter:id,name')->withCount('photos')
            ->latest('observed_at')->paginate(30);
    }

    /**
     * Send a report (multipart, with up to 5 `photos[]`). Send a `client_ref` (UUID made on the
     * phone) so a report queued offline and sent twice is stored once: a repeat returns the
     * stored report with 200 instead of 201.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);

        if ($request->filled('client_ref') && ($existing = Observation::query()->where('client_ref', $request->string('client_ref')->toString())->first())) {
            return response()->json($existing);
        }

        $data = $request->validate(Observation::reportRules($user, $request->integer('site_id')));

        return response()->json(Observation::report($user, $data, $data['photos'] ?? []), 201);
    }

    /**
     * One report with its photos (`GET /photos/{id}`) and actions.
     */
    public function show(Request $request, Observation $observation): Observation
    {
        abort_unless($observation->canBeViewedBy($this->user($request)), 404);

        return $observation->load('site:id,code,name', 'area:id,name', 'reporter:id,name', 'contractor:id,name',
            'photos:id,photoable_type,photoable_id', 'actions.owner:id,name');
    }
}
