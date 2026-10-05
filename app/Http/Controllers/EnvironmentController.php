<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Site;
use App\Models\SiteMetric;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The environmental log: monthly waste, water, energy and fuel per site, with spills
 * (environmental-release incidents) alongside.
 */
class EnvironmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $sites = Site::query()->availableTo($user)->get(['id', 'code', 'name']);
        $site = $sites->firstWhere('id', $request->integer('site_id')) ?? $sites->first();
        $year = $request->integer('year') ?: now()->year;
        $start = CarbonImmutable::create($year)->startOfYear();

        $values = SiteMetric::query()->where('site_id', $site?->id)->whereIn('metric', array_keys(SiteMetric::environmental()))
            ->whereYear('month', $year)->get()
            ->mapWithKeys(fn (SiteMetric $m) => ["{$m->metric}|{$m->month->format('Y-m')}" => (float) $m->value]);
        $spills = Incident::query()->where('site_id', $site?->id)->where('type', 'environmental-release')->whereYear('occurred_at', $year)
            ->pluck('occurred_at')->countBy(fn ($at) => CarbonImmutable::parse($at)->format('Y-m'));

        $totals = collect(SiteMetric::environmental())->map(fn ($meta, string $metric) => $values->filter(fn ($v, string $key) => str_starts_with($key, "{$metric}|"))->sum());
        $waste = $totals['general-waste'] + $totals['hazardous-waste'] + $totals['recycled-waste'];

        return Inertia::render('environment/index', [
            'sites' => $sites,
            'site' => $site,
            'year' => $year,
            'months' => collect(range(0, 11))->map(fn (int $n) => $start->addMonths($n)->format('Y-m')),
            'metrics' => collect(SiteMetric::environmental())->map(fn (array $meta, string $key) => ['key' => $key, 'label' => $meta[0], 'unit' => $meta[1]])->values(),
            'values' => $values,
            'totals' => $totals,
            'spills' => $spills,
            'recyclingRate' => $waste > 0 ? round(100 * $totals['recycled-waste'] / $waste) : null,
        ]);
    }

    /**
     * Record (or correct) one month's figure for a site.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'site_id' => ['required', Rule::exists('sites', 'id')->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'metric' => ['required', Rule::in(array_keys(SiteMetric::environmental()))],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        SiteMetric::query()->updateOrCreate(
            ['site_id' => $data['site_id'], 'month' => Carbon::createFromFormat('Y-m-d', $data['month'].'-01')?->toDateString(), 'metric' => $data['metric']],
            ['value' => $data['value']],
        );

        return $this->done(__('Figure saved.'));
    }
}
