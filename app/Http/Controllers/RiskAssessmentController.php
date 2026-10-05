<?php

namespace App\Http\Controllers;

use App\Models\RiskAssessment;
use App\Models\RiskHazard;
use App\Models\Site;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RiskAssessmentController extends Controller
{
    /**
     * The register shows the current revision of each assessment: the draft if one is being
     * written, otherwise the approved one. Superseded revisions are reached from their successor.
     */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $query = RiskAssessment::query()->visibleTo($user)
            ->with('site:id,code', 'area:id,name')
            ->withCount('hazards')
            ->where('status', '!=', 'superseded')
            ->whereNotExists(fn ($q) => $q->from('risk_assessments as newer')
                ->whereColumn('newer.number', 'risk_assessments.number')
                ->whereColumn('newer.revision', '>', 'risk_assessments.revision')
                ->where('newer.status', 'draft'))
            ->when(in_array($request->input('type'), RiskAssessment::TYPES, true), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($request->input('review') === 'required', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('review_required', true)
                ->orWhere(fn (Builder $q) => $q->where('status', 'approved')->whereDate('review_due_on', '<', today()))))
            ->when($request->filled('site_id'), fn (Builder $q) => $q->where('site_id', $request->integer('site_id')));
        $counts = TableQuery::tabs($query, 'status', ['draft', 'approved']);

        return Inertia::render('risk-assessments/index', [
            'assessments' => TableQuery::paginate(
                $query->when(in_array($request->input('status'), ['draft', 'approved'], true), fn (Builder $q) => $q->where('status', $request->input('status'))),
                $request, ['number', 'title', 'activity'], ['number', 'review_due_on', 'created_at'], 'number',
            ),
            'counts' => $counts,
            'sites' => Site::query()->availableTo($user)->with('areas:id,site_id,name')->get(['id', 'code', 'name']),
            'filters' => TableQuery::filters($request, ['status', 'type', 'review', 'site_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $assessment = RiskAssessment::create($this->detailData($request));

        return to_route('risk-assessments.show', $assessment);
    }

    public function show(Request $request, RiskAssessment $riskAssessment): Response
    {
        abort_unless($riskAssessment->isVisibleTo($this->user($request)), 404);

        $riskAssessment->load('site:id,code,name', 'site.areas:id,site_id,name', 'area:id,name', 'hazards', 'creator:id,name', 'approver:id,name',
            'incidents:id,risk_assessment_id,number,title,classification');

        return Inertia::render('risk-assessments/show', [
            'assessment' => [...$riskAssessment->toArray(), 'review_overdue' => $riskAssessment->isReviewOverdue()],
            'revisions' => RiskAssessment::query()->where('number', $riskAssessment->number)->orderByDesc('revision')
                ->get(['id', 'revision', 'status', 'approved_at']),
        ]);
    }

    public function update(Request $request, RiskAssessment $riskAssessment): RedirectResponse
    {
        $this->draft($request, $riskAssessment);
        $riskAssessment->update($this->detailData($request, $riskAssessment));

        return $this->done(__('Assessment updated.'));
    }

    public function storeHazard(Request $request, RiskAssessment $riskAssessment): RedirectResponse
    {
        $this->draft($request, $riskAssessment);
        $riskAssessment->hazards()->create($this->hazardData($request));

        return $this->done(__('Hazard added.'));
    }

    public function updateHazard(Request $request, RiskAssessment $riskAssessment, RiskHazard $hazard): RedirectResponse
    {
        $this->draft($request, $riskAssessment);
        abort_unless($hazard->risk_assessment_id === $riskAssessment->id, 404);
        $hazard->update($this->hazardData($request));

        return $this->done(__('Hazard updated.'));
    }

    public function destroyHazard(Request $request, RiskAssessment $riskAssessment, RiskHazard $hazard): RedirectResponse
    {
        $this->draft($request, $riskAssessment);
        abort_unless($hazard->risk_assessment_id === $riskAssessment->id, 404);
        $hazard->delete();

        return $this->done(__('Hazard removed.'));
    }

    public function approve(Request $request, RiskAssessment $riskAssessment): RedirectResponse
    {
        abort_unless($riskAssessment->isVisibleTo($this->user($request)), 404);
        $riskAssessment->approve($this->user($request));

        return $this->done(__('Assessment approved.'));
    }

    public function revise(Request $request, RiskAssessment $riskAssessment): RedirectResponse
    {
        abort_unless($riskAssessment->isVisibleTo($this->user($request)), 404);
        $draft = $riskAssessment->revise();
        $this->toast('success', __('Revision :r started as a draft.', ['r' => $draft->revision]));

        return to_route('risk-assessments.show', $draft);
    }

    /**
     * Reviewed, and no change is needed: clear the flag (the audit trail records who decided).
     */
    public function clearReview(Request $request, RiskAssessment $riskAssessment): RedirectResponse
    {
        abort_unless($riskAssessment->isVisibleTo($this->user($request)), 404);
        $riskAssessment->forceFill(['review_required' => false, 'review_reason' => null])->save();

        return $this->done(__('Review cleared.'));
    }

    public function destroy(Request $request, RiskAssessment $riskAssessment): RedirectResponse
    {
        $this->draft($request, $riskAssessment);
        $previous = $riskAssessment->previous_id;
        $riskAssessment->delete();

        return $previous ? to_route('risk-assessments.show', $previous) : to_route('risk-assessments.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(Request $request, ?RiskAssessment $assessment = null): array
    {
        $user = $this->user($request);
        $siteId = $assessment->site_id ?? $request->integer('site_id');
        $data = $request->validate([
            'site_id' => [$assessment ? 'prohibited' : 'required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('site_id', $siteId)],
            'type' => ['required', Rule::in(RiskAssessment::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'activity' => ['required', 'string', 'max:5000'],
            'review_due_on' => ['required', 'date', 'after:today'],
        ]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function hazardData(Request $request): array
    {
        $score = ['required', 'integer', 'between:1,5'];

        return $request->validate([
            'hazard' => ['required', 'string', 'max:255'],
            'who_at_risk' => ['nullable', 'string', 'max:255'],
            'existing_controls' => ['nullable', 'string', 'max:5000'],
            'likelihood' => $score,
            'severity' => $score,
            'additional_controls' => ['nullable', 'string', 'max:5000'],
            'residual_likelihood' => $score,
            'residual_severity' => $score,
        ]);
    }

    /**
     * Only a draft is edited; approved and superseded revisions are the record.
     */
    private function draft(Request $request, RiskAssessment $assessment): void
    {
        abort_unless($assessment->isVisibleTo($this->user($request)), 404);

        if ($assessment->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('Only a draft can be changed. Start a new revision instead.')]);
        }
    }
}
