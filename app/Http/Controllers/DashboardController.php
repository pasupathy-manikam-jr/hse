<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteMetric;
use App\Support\Kpis;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public const PERIODS = ['12m', 'ytd'];

    /**
     * KPIs for the last 12 months (or the year to date), for one site or all the user's sites.
     */
    public function __invoke(Request $request): Response
    {
        $user = $this->user($request);
        $period = in_array($request->input('period'), self::PERIODS, true) ? $request->input('period') : '12m';
        $to = CarbonImmutable::today();
        $from = $period === 'ytd' ? $to->startOfYear() : $to->startOfMonth()->subMonths(11);
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $kpis = new Kpis($user, $from, $to, $siteId);

        return Inertia::render('dashboard', [
            'period' => ['key' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'siteId' => $siteId,
            'sites' => Site::query()->availableTo($user)->get(['id', 'code', 'name']),
            'lagging' => $kpis->lagging(),
            'daysSinceLti' => $kpis->daysSinceLti(),
            'leading' => $kpis->leading(),
            'tasks' => $kpis->tasks(),
            'hours' => $user->can('edit-sites')
                ? SiteMetric::query()->where('metric', SiteMetric::HOURS)->whereIn('site_id', Site::query()->availableTo($user)->select('id'))
                    ->where('month', '>=', $to->startOfMonth()->subMonths(11)->toDateString())
                    ->orderByDesc('month')->get(['id', 'site_id', 'month', 'value as hours'])
                : [],
        ]);
    }

    /**
     * Record (or correct) the hours worked at a site in a month.
     */
    public function storeHours(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'site_id' => ['required', Rule::exists('sites', 'id')->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'hours' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        SiteMetric::query()->updateOrCreate(
            ['site_id' => $data['site_id'], 'month' => Carbon::createFromFormat('Y-m-d', $data['month'].'-01')?->toDateString(), 'metric' => SiteMetric::HOURS],
            ['value' => $data['hours']],
        );

        return $this->done(__('Hours saved.'));
    }
}
