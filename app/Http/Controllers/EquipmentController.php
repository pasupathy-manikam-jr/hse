<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentInspection;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The equipment register: what needs a periodic inspection, when it is next due, and the history of
 * inspections with their certificates.
 */
class EquipmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $sites = Site::query()->availableTo($user)->with('areas:id,site_id,name')->get(['id', 'code', 'name']);
        $query = Equipment::query()->visibleTo($user)->with('site:id,code', 'area:id,name', 'owner:id,name', 'inspections.creator:id,name')
            ->when(in_array($request->input('type'), Equipment::TYPES, true), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($request->filled('site_id'), fn (Builder $q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->input('due') === 'overdue', fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('next_inspection_on')->orWhereDate('next_inspection_on', '<', today())))
            ->when($request->input('due') === 'soon', fn (Builder $q) => $q->whereBetween('next_inspection_on', [today()->toDateString(), today()->addDays(Equipment::DUE_SOON_DAYS)->toDateString()]));
        $counts = TableQuery::tabs($query, 'status', Equipment::STATUSES);

        return Inertia::render('equipment/index', [
            'equipment' => TableQuery::paginate(
                $query->when(in_array($request->input('status'), Equipment::STATUSES, true), fn (Builder $q) => $q->where('status', $request->input('status'))),
                $request, ['asset_tag', 'name', 'serial_number'], ['asset_tag', 'name', 'next_inspection_on'], 'next_inspection_on',
            ),
            'counts' => $counts,
            'sites' => $sites,
            'people' => User::query()->where(fn (Builder $q) => $q->whereNull('site_id')->orWhereIn('site_id', $sites->pluck('id')))
                ->orderBy('name')->get(['id', 'name', 'site_id']),
            'dueSoonDays' => Equipment::DUE_SOON_DAYS,
            'filters' => TableQuery::filters($request, ['status', 'type', 'site_id', 'due']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $equipment = Equipment::create($this->validated($request));

        return $this->done(__(':tag added to the register.', ['tag' => $equipment->asset_tag]));
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        abort_unless($equipment->isVisibleTo($this->user($request)), 404);
        $equipment->update(Arr::except($this->validated($request, $equipment), 'site_id'));

        return $this->done(__('Equipment updated.'));
    }

    public function destroy(Request $request, Equipment $equipment): RedirectResponse
    {
        abort_unless($equipment->isVisibleTo($this->user($request)), 404);

        if ($equipment->permits()->exists()) {
            return $this->toast('error', __('Permits list this equipment. Mark it out of service instead.'));
        }

        $equipment->delete();

        return $this->done(__('Equipment removed from the register.'));
    }

    /**
     * POST: the form may carry the certificate.
     */
    public function storeInspection(Request $request, Equipment $equipment): RedirectResponse
    {
        abort_unless($equipment->isVisibleTo($this->user($request)), 404);
        $data = $request->validate([
            'inspected_on' => ['required', 'date', 'before_or_equal:today'],
            'passed' => ['required', 'boolean'],
            'inspector' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'certificate' => EquipmentInspection::uploadRules(),
        ]);

        $equipment->recordInspection([
            'inspected_on' => $request->string('inspected_on')->toString(),
            'passed' => $request->boolean('passed'),
            'inspector' => $request->string('inspector')->toString(),
            'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
        ], $request->file('certificate'));

        return $this->done($data['passed'] ? __('Inspection recorded.') : __('Failed inspection recorded; :tag is out of service.', ['tag' => $equipment->asset_tag]));
    }

    public function certificate(Request $request, Equipment $equipment, EquipmentInspection $inspection): StreamedResponse
    {
        abort_unless($inspection->equipment_id === $equipment->id && $equipment->isVisibleTo($this->user($request)), 404);

        return $inspection->downloadUpload();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Equipment $equipment = null): array
    {
        $user = $this->user($request);
        $siteId = $equipment->site_id ?? $request->integer('site_id');

        return $request->validate([
            'site_id' => [$equipment ? 'nullable' : 'required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('site_id', $siteId)],
            'asset_tag' => ['required', 'string', 'max:50', Rule::unique('equipment')->ignore($equipment)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Equipment::TYPES)],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $siteId))],
            'inspection_interval_months' => ['required', 'integer', 'between:1,60'],
            'status' => ['required', Rule::in(Equipment::STATUSES)],
        ]);
    }
}
