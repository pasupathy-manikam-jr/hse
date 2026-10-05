<?php

namespace App\Http\Controllers;

use App\Models\Chemical;
use App\Models\RiskAssessment;
use App\Models\Site;
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
 * The chemical register. Everyone who works with chemicals can read it and open the SDS.
 */
class ChemicalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $sites = Site::query()->availableTo($user)->with('areas:id,site_id,name')->get(['id', 'code', 'name']);
        $query = Chemical::query()->visibleTo($user)->with('site:id,code', 'area:id,name', 'riskAssessment:id,number,status')
            ->when(in_array($request->input('hazard'), Chemical::HAZARDS, true), fn (Builder $q) => $q->whereJsonContains('hazards', $request->input('hazard')))
            ->when($request->filled('site_id'), fn (Builder $q) => $q->where('site_id', $request->integer('site_id')));

        return Inertia::render('chemicals/index', [
            'chemicals' => TableQuery::paginate($query, $request, ['name', 'supplier', 'product_code'], ['name', 'sds_issued_on'], 'name'),
            'sites' => $sites,
            'assessments' => RiskAssessment::query()->whereIn('site_id', $sites->pluck('id'))->where('status', 'approved')->where('type', 'coshh')
                ->orderBy('number')->get(['id', 'site_id', 'number', 'title']),
            'hazards' => Chemical::HAZARDS,
            'filters' => TableQuery::filters($request, ['hazard', 'site_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $chemical = new Chemical($this->validated($request));
        $this->attachSds($request, $chemical);
        $chemical->save();

        return $this->done(__(':name added to the register.', ['name' => $chemical->name]));
    }

    /**
     * POST: the form may carry a new SDS file.
     */
    public function update(Request $request, Chemical $chemical): RedirectResponse
    {
        abort_unless($chemical->isVisibleTo($this->user($request)), 404);
        $chemical->fill(Arr::except($this->validated($request, $chemical), 'site_id'));
        $this->attachSds($request, $chemical);
        $chemical->save();

        return $this->done(__('Chemical updated.'));
    }

    public function destroy(Request $request, Chemical $chemical): RedirectResponse
    {
        abort_unless($chemical->isVisibleTo($this->user($request)), 404);
        $chemical->delete();

        return $this->done(__('Chemical removed from the register.'));
    }

    public function sds(Request $request, Chemical $chemical): StreamedResponse
    {
        abort_unless($chemical->isVisibleTo($this->user($request)), 404);

        return $chemical->downloadUpload();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Chemical $chemical = null): array
    {
        $user = $this->user($request);
        $siteId = $chemical->site_id ?? $request->integer('site_id');

        $data = $request->validate([
            'site_id' => [$chemical ? 'nullable' : 'required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('site_id', $siteId)],
            'name' => ['required', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'product_code' => ['nullable', 'string', 'max:100'],
            'hazards' => ['nullable', 'array'],
            'hazards.*' => [Rule::in(Chemical::HAZARDS)],
            'max_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:10'],
            'sds_issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'risk_assessment_id' => ['nullable', Rule::exists('risk_assessments', 'id')->where('site_id', $siteId)->where('type', 'coshh')],
            'sds' => Chemical::uploadRules(),
        ]);

        // The file is stored by attachSds(), not mass-assigned.
        return Arr::except($data, 'sds');
    }

    private function attachSds(Request $request, Chemical $chemical): void
    {
        if ($request->hasFile('sds')) {
            $chemical->attachUpload($request->file('sds'));
        }
    }
}
