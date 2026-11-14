<?php

namespace App\Support;

use App\Models\Action;
use App\Models\Equipment;
use App\Models\Incident;
use App\Models\Inspection;
use App\Models\Observation;
use App\Models\Permit;
use App\Models\ToolboxTalk;
use Carbon\CarbonImmutable;

/**
 * The weekly HSE figures (Monday to Sunday) for a set of sites: shown on the report page and
 * emailed to each site's recipients on Monday morning.
 */
class WeeklyReport
{
    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    /**
     * @param  list<int>  $siteIds
     */
    public function __construct(private array $siteIds, CarbonImmutable $day)
    {
        $this->from = $day->startOfWeek();
        $this->to = $this->from->endOfWeek();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $ids = $this->siteIds;
        $inWeek = [$this->from, $this->to];
        $observations = Observation::query()->counted()->whereIn('site_id', $ids)->whereBetween('observed_at', $inWeek);
        $inspections = Inspection::query()->whereIn('site_id', $ids)->where('status', 'completed')->whereBetween('completed_at', $inWeek);
        $talks = ToolboxTalk::query()->whereIn('site_id', $ids)->whereBetween('held_on', [$this->from->toDateString(), $this->to->toDateString()]);

        return [
            'week' => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'number' => $this->from->isoWeek(), 'year' => $this->from->isoWeekYear()],
            'observations' => (clone $observations)->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'topics' => (clone $observations)->whereNotNull('topic')->selectRaw('topic, count(*) as total')->groupBy('topic')->orderByDesc('total')->pluck('total', 'topic'),
            'incidents' => Incident::query()->whereIn('site_id', $ids)->whereBetween('occurred_at', $inWeek)->with('site:id,code')
                ->orderBy('occurred_at')->get(['id', 'number', 'site_id', 'type', 'title', 'occurred_at', 'classification', 'recordable', 'status']),
            'prevention' => [
                'inspections' => (clone $inspections)->count(),
                'inspection_score' => (clone $inspections)->avg('score'),
                'toolbox_talks' => (clone $talks)->count(),
                'attendees' => (clone $talks)->withCount('attendees')->get()->sum('attendees_count'),
                'permits' => Permit::query()->whereIn('site_id', $ids)->whereBetween('approved_at', $inWeek)->count(),
            ],
            'actions' => [
                'raised' => Action::query()->whereIn('site_id', $ids)->whereBetween('created_at', $inWeek)->count(),
                'verified' => Action::query()->whereIn('site_id', $ids)->whereBetween('verified_at', $inWeek)->count(),
                'open' => Action::query()->whereIn('site_id', $ids)->where('status', '!=', 'verified')->count(),
                'overdue' => Action::query()->whereIn('site_id', $ids)->overdue()->with('owner:id,name')->orderBy('due_on')
                    ->get(['id', 'number', 'description', 'owner_id', 'due_on']),
            ],
            'equipmentOverdue' => Equipment::query()->whereIn('site_id', $ids)->where('status', 'in-service')
                ->where(fn ($q) => $q->whereNull('next_inspection_on')->orWhereDate('next_inspection_on', '<', today()))
                ->with('site:id,code')->orderBy('asset_tag')->get(['id', 'site_id', 'asset_tag', 'name', 'next_inspection_on']),
            'daysSinceLti' => Kpis::daysSinceLtiAt($ids),
        ];
    }
}
