<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteMetric;
use App\Models\User;
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
            'reporting' => $kpis->reporting(),
            'hours' => $user->can('edit-sites') ? $this->monthlyFigures($user, $to) : [],
        ]);
    }

    /**
     * The last 12 months of hours worked per site, with the average workers beside them.
     *
     * @return list<array{id: int, site_id: int, month: string, hours: string, workers: string|null}>
     */
    private function monthlyFigures(User $user, CarbonImmutable $to): array
    {
        $rows = SiteMetric::query()->whereIn('metric', [SiteMetric::HOURS, SiteMetric::WORKERS])
            ->whereIn('site_id', Site::query()->availableTo($user)->select('id'))
            ->where('month', '>=', $to->startOfMonth()->subMonths(11)->toDateString())
            ->orderByDesc('month')->get();
        $workers = $rows->where('metric', SiteMetric::WORKERS)->keyBy(fn (SiteMetric $m) => "{$m->site_id}|{$m->month->format('Y-m')}");

        return array_values($rows->where('metric', SiteMetric::HOURS)->map(fn (SiteMetric $m) => [
            'id' => $m->id,
            'site_id' => $m->site_id,
            'month' => $m->month->format('Y-m'),
            'hours' => $m->value,
            'workers' => $workers->get("{$m->site_id}|{$m->month->format('Y-m')}")?->value,
        ])->all());
    }

    /**
     * Record (or correct) the hours worked at a site in a month, and the average workers.
     */
    public function storeHours(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'site_id' => ['required', Rule::exists('sites', 'id')->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'hours' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'workers' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);
        $month = Carbon::createFromFormat('Y-m-d', $data['month'].'-01')?->toDateString();

        SiteMetric::query()->updateOrCreate(['site_id' => $data['site_id'], 'month' => $month, 'metric' => SiteMetric::HOURS], ['value' => $data['hours']]);

        // The average headcount, for the JKKP 8 rates per 1,000 workers.
        if (isset($data['workers'])) {
            SiteMetric::query()->updateOrCreate(['site_id' => $data['site_id'], 'month' => $month, 'metric' => SiteMetric::WORKERS], ['value' => $data['workers']]);
        }

        return $this->done(__('Hours saved.'));
    }
}
