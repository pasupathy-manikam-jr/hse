<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\WeeklyReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The weekly HSE report page for one site or all the user's sites. Printed from the browser.
 */
class WeeklyReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $this->user($request);
        $day = rescue(fn () => CarbonImmutable::parse((string) $request->input('week')), null, false) ?? CarbonImmutable::today();
        $sites = Site::query()->availableTo($user)->get(['id', 'code', 'name']);
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $siteIds = array_values($sites->pluck('id')->map(fn ($id) => (int) $id)->when($siteId, fn ($ids) => $ids->intersect([$siteId]))->all());

        return Inertia::render('reports/weekly', [
            'siteId' => $siteId,
            'sites' => $sites,
            ...(new WeeklyReport($siteIds, $day))->data(),
        ]);
    }
}
