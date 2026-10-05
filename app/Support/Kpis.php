<?php

namespace App\Support;

use App\Models\Action;
use App\Models\Document;
use App\Models\Incident;
use App\Models\IncidentPerson;
use App\Models\Inspection;
use App\Models\Observation;
use App\Models\Permit;
use App\Models\RiskAssessment;
use App\Models\ShiftHandover;
use App\Models\Site;
use App\Models\SiteMetric;
use App\Models\ToolboxTalk;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The dashboard figures for the sites a user can see, over one period. Injury rates count
 * injured people (a case per person, as on the OSHA log), not incidents.
 */
class Kpis
{
    /** LTIFR: lost-time injuries per million hours worked. */
    public const LTIFR_BASE = 1_000_000;

    /** TRIR: recordable injuries per 200,000 hours (100 full-time workers for a year). */
    public const TRIR_BASE = 200_000;

    public const LOST_TIME = ['lost-time', 'fatality'];

    /** @var list<int> */
    private array $siteIds;

    public function __construct(
        private User $user,
        private CarbonImmutable $from,
        private CarbonImmutable $to,
        ?int $siteId = null,
    ) {
        $this->siteIds = array_values(Site::query()->availableTo($user)
            ->when($siteId, fn (Builder $q) => $q->whereKey($siteId))
            ->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function lagging(): array
    {
        $hours = (float) SiteMetric::query()->whereIn('site_id', $this->siteIds)->where('metric', SiteMetric::HOURS)
            ->whereBetween('month', [$this->from->startOfMonth()->toDateString(), $this->to->toDateString()])->sum('value');
        $injuries = $this->injured()->pluck('treatment');
        $lti = $injuries->filter(fn (?string $t) => in_array($t, self::LOST_TIME, true))->count();
        $recordable = $injuries->filter(fn (?string $t) => in_array($t, Incident::RECORDABLE, true))->count();

        return [
            'hours' => $hours,
            'lti' => $lti,
            'recordable' => $recordable,
            'ltifr' => $hours > 0 ? round($lti * self::LTIFR_BASE / $hours, 2) : null,
            'trir' => $hours > 0 ? round($recordable * self::TRIR_BASE / $hours, 2) : null,
            'pyramid' => [
                'near-miss' => Observation::query()->whereIn('site_id', $this->siteIds)->where('type', 'near-miss')
                    ->whereBetween('observed_at', [$this->from, $this->to->endOfDay()])->count(),
                ...collect(['first-aid', 'medical', 'restricted', 'lost-time', 'fatality'])
                    ->mapWithKeys(fn (string $t) => [$t => $injuries->filter(fn (?string $x) => $x === $t)->count()])->all(),
            ],
        ];
    }

    /**
     * Whole days since each site's last lost-time injury (null: none recorded).
     *
     * @return list<array{site: string, days: int|null}>
     */
    public function daysSinceLti(): array
    {
        $last = Incident::query()->whereIn('site_id', $this->siteIds)
            ->whereHas('people', fn (Builder $q) => $q->where('role', 'injured')->whereIn('treatment', self::LOST_TIME))
            ->selectRaw('site_id, max(occurred_at) as last_at')->groupBy('site_id')->pluck('last_at', 'site_id');

        return array_values(Site::query()->whereKey($this->siteIds)->orderBy('code')->get(['id', 'code'])
            ->map(fn (Site $s) => [
                'site' => $s->code,
                'days' => isset($last[$s->id]) ? (int) CarbonImmutable::parse($last[$s->id])->startOfDay()->diffInDays(today()) : null,
            ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function leading(): array
    {
        $completed = Inspection::query()->whereIn('site_id', $this->siteIds)->where('status', 'completed')
            ->whereBetween('completed_at', [$this->from, $this->to->endOfDay()]);
        $start = $this->to->startOfMonth()->subMonths(5);
        $observed = Observation::query()->whereIn('site_id', $this->siteIds)
            ->where('observed_at', '>=', $start)->pluck('observed_at')
            ->countBy(fn ($at) => CarbonImmutable::parse($at)->format('Y-m'));

        return [
            'observations_by_month' => collect(range(0, 5))->map(fn (int $n) => [
                'month' => $start->addMonths($n)->format('Y-m'),
                'count' => $observed[$start->addMonths($n)->format('Y-m')] ?? 0,
            ])->all(),
            'inspections' => (clone $completed)->count(),
            'inspection_score' => ($score = (clone $completed)->avg('score')) === null ? null : (int) round((float) $score),
            'toolbox_talks' => ToolboxTalk::query()->whereIn('site_id', $this->siteIds)
                ->whereBetween('held_on', [$this->from->toDateString(), $this->to->toDateString()])->count(),
            'actions_open' => Action::query()->whereIn('site_id', $this->siteIds)->where('status', '!=', 'verified')->count(),
            'actions_overdue' => Action::query()->whereIn('site_id', $this->siteIds)->overdue()->count(),
        ];
    }

    /**
     * What is waiting for this user now, regardless of the period.
     *
     * @return array<string, int>
     */
    public function tasks(): array
    {
        $u = $this->user;

        return array_filter([
            'my_actions' => Action::query()->where('owner_id', $u->id)->where('status', 'open')->count(),
            'to_verify' => $u->can('verify-actions')
                ? Action::query()->visibleTo($u)->where('status', 'done')->where('owner_id', '!=', $u->id)->count() : 0,
            'permits_to_approve' => $u->can('approve-permits')
                ? Permit::query()->visibleTo($u)->where('status', 'requested')->where('created_by', '!=', $u->id)->count() : 0,
            'assessments_to_review' => $u->can('approve-risk-assessments')
                ? RiskAssessment::query()->visibleTo($u)->where(fn (Builder $q) => $q->where('review_required', true)->orWhere('status', 'draft'))->count() : 0,
            'documents_to_read' => $u->readings()->where('status', 'effective')->wherePivotNull('acknowledged_at')->count(),
            'documents_to_review' => Document::query()->where('owner_id', $u->id)->dueForReview()->count(),
            'handovers_to_acknowledge' => ShiftHandover::query()->where('to_user_id', $u->id)->whereNull('acknowledged_at')->count(),
        ]);
    }

    /**
     * Injured people on incidents at these sites in the period.
     *
     * @return Collection<int, IncidentPerson>
     */
    private function injured(): Collection
    {
        return IncidentPerson::query()->where('role', 'injured')
            ->whereHas('incident', fn (Builder $q) => $q->whereIn('site_id', $this->siteIds)->whereBetween('occurred_at', [$this->from, $this->to->endOfDay()]))
            ->get(['id', 'treatment']);
    }
}
