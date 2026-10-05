<?php

use App\Models\Incident;
use App\Models\Observation;
use App\Models\Site;
use App\Models\SiteMetric;
use App\Support\Kpis;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->other = Site::create(['code' => 'SA', 'name' => 'Plant']);
});

function injury(Site $site, string $treatment, string $when): void
{
    Incident::create(['site_id' => $site->id, 'type' => 'injury', 'title' => 'x', 'description' => 'x', 'occurred_at' => $when])
        ->people()->create(['name' => 'Ali', 'role' => 'injured', 'treatment' => $treatment]);
}

test('injury rates are computed from injured people and hours worked, per OSHA bases', function () {
    // 500,000 hours in the period: 2 lost-time cases, 1 more recordable, 1 first aid.
    SiteMetric::create(['site_id' => $this->site->id, 'month' => '2026-03-01', 'metric' => SiteMetric::HOURS, 'value' => 300_000]);
    SiteMetric::create(['site_id' => $this->site->id, 'month' => '2026-04-01', 'metric' => SiteMetric::HOURS, 'value' => 200_000]);
    SiteMetric::create(['site_id' => $this->site->id, 'month' => '2025-01-01', 'metric' => SiteMetric::HOURS, 'value' => 999_999]);
    injury($this->site, 'lost-time', '2026-03-10 09:00');
    injury($this->site, 'fatality', '2026-04-02 09:00');
    injury($this->site, 'medical', '2026-04-05 09:00');
    injury($this->site, 'first-aid', '2026-04-06 09:00');
    injury($this->site, 'lost-time', '2025-02-01 09:00');
    Observation::create(['site_id' => $this->site->id, 'type' => 'near-miss', 'potential' => 'high', 'description' => 'x', 'observed_at' => '2026-03-15 10:00']);

    $kpis = (new Kpis($this->userWithRole('hse-manager'), CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-04-30')))->lagging();

    expect($kpis)->hours->toBe(500000.0)->lti->toBe(2)->recordable->toBe(3)
        ->ltifr->toBe(4.0)       // 2 × 1,000,000 / 500,000
        ->trir->toBe(1.2)        // 3 × 200,000 / 500,000
        ->and($kpis['pyramid'])->toBe(['near-miss' => 1, 'first-aid' => 1, 'medical' => 1, 'restricted' => 0, 'lost-time' => 1, 'fatality' => 1]);
});

test('rates are left blank without hours, and a site user only sees their site', function () {
    injury($this->site, 'lost-time', now()->subDays(3)->toDateTimeString());
    injury($this->other, 'lost-time', now()->subDays(40)->toDateTimeString());
    $user = $this->userWithRole('supervisor');
    $user->update(['site_id' => $this->site->id]);

    $kpis = new Kpis($user, today()->subYear(), today());

    expect($kpis->lagging())->ltifr->toBeNull()->trir->toBeNull()->lti->toBe(1)
        ->and($kpis->daysSinceLti())->toBe([['site' => 'KL', 'days' => 3]]);
});

test('the dashboard shows the KPIs and what needs the user', function () {
    $this->actingAs($this->userWithRole('hse-manager'))->get(route('dashboard', ['period' => 'ytd']))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('period.key', 'ytd')
            ->where('period.from', today()->startOfYear()->toDateString())
            ->has('lagging.pyramid')
            ->has('leading.observations_by_month', 6));
});

test('hours are entered per site and month, and saving again corrects them', function () {
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->post(route('dashboard.hours'), ['site_id' => $this->site->id, 'month' => now()->format('Y-m'), 'hours' => 1200])->assertSessionHasNoErrors();
    $this->post(route('dashboard.hours'), ['site_id' => $this->site->id, 'month' => now()->format('Y-m'), 'hours' => 1500])->assertSessionHasNoErrors();
    $this->post(route('dashboard.hours'), ['site_id' => $this->site->id, 'month' => now()->addMonth()->format('Y-m'), 'hours' => 1])->assertSessionHasErrors('month');

    $metric = SiteMetric::query()->sole();
    expect($metric->value)->toBe('1500.00')->and($metric->metric)->toBe(SiteMetric::HOURS);
    $this->actingAs($this->userWithRole('supervisor'))->post(route('dashboard.hours'), ['site_id' => $this->site->id, 'month' => now()->format('Y-m'), 'hours' => 1])->assertForbidden();
});

test('workers see the dashboard too', function () {
    $this->actingAs($this->userWithRole('worker'))->get(route('dashboard'))->assertOk();
});
