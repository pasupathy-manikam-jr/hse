<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\Equipment;
use App\Models\Incident;
use App\Models\Inspection;
use App\Models\Observation;
use App\Models\Permit;
use App\Models\Site;
use App\Models\ToolboxTalk;
use App\Support\Kpis;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The weekly HSE report (Monday to Sunday) for one site or all the user's sites: what was reported,
 * what prevention work was done, and what is still open. Printed from the browser.
 */
class WeeklyReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $this->user($request);
        $week = rescue(fn () => CarbonImmutable::parse((string) $request->input('week')), null, false) ?? CarbonImmutable::today();
        $from = $week->startOfWeek();
        $to = $from->endOfWeek();
        $sites = Site::query()->availableTo($user)->get(['id', 'code', 'name']);
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $siteIds = $sites->pluck('id')->when($siteId, fn ($ids) => $ids->intersect([$siteId]))->values()->all();
        $inWeek = [$from, $to];

        $inspections = Inspection::query()->whereIn('site_id', $siteIds)->where('status', 'completed')->whereBetween('completed_at', $inWeek);
        $talks = ToolboxTalk::query()->whereIn('site_id', $siteIds)->whereBetween('held_on', [$from->toDateString(), $to->toDateString()]);

        return Inertia::render('reports/weekly', [
            'week' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'number' => $from->isoWeek(), 'year' => $from->isoWeekYear()],
            'siteId' => $siteId,
            'sites' => $sites,
            'observations' => Observation::query()->whereIn('site_id', $siteIds)->whereBetween('observed_at', $inWeek)
                ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'incidents' => Incident::query()->whereIn('site_id', $siteIds)->whereBetween('occurred_at', $inWeek)->with('site:id,code')
                ->orderBy('occurred_at')->get(['id', 'number', 'site_id', 'type', 'title', 'occurred_at', 'classification', 'recordable', 'status']),
            'prevention' => [
                'inspections' => (clone $inspections)->count(),
                'inspection_score' => (clone $inspections)->avg('score'),
                'toolbox_talks' => (clone $talks)->count(),
                'attendees' => (clone $talks)->withCount('attendees')->get()->sum('attendees_count'),
                'permits' => Permit::query()->whereIn('site_id', $siteIds)->whereBetween('approved_at', $inWeek)->count(),
            ],
            'actions' => [
                'raised' => Action::query()->whereIn('site_id', $siteIds)->whereBetween('created_at', $inWeek)->count(),
                'verified' => Action::query()->whereIn('site_id', $siteIds)->whereBetween('verified_at', $inWeek)->count(),
                'open' => Action::query()->whereIn('site_id', $siteIds)->where('status', '!=', 'verified')->count(),
                'overdue' => Action::query()->whereIn('site_id', $siteIds)->overdue()->with('owner:id,name')->orderBy('due_on')
                    ->get(['id', 'number', 'description', 'owner_id', 'due_on']),
            ],
            'equipmentOverdue' => Equipment::query()->whereIn('site_id', $siteIds)->where('status', 'in-service')
                ->where(fn ($q) => $q->whereNull('next_inspection_on')->orWhereDate('next_inspection_on', '<', today()))
                ->with('site:id,code')->orderBy('asset_tag')->get(['id', 'site_id', 'asset_tag', 'name', 'next_inspection_on']),
            'daysSinceLti' => (new Kpis($user, $from, $to, $siteId))->daysSinceLti(),
        ]);
    }
}
