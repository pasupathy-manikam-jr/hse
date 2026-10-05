<?php

use App\Models\Incident;
use App\Models\Site;
use App\Models\SiteMetric;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
});

test('monthly figures are recorded per metric, and saving again corrects them', function () {
    $this->actingAs($this->userWithRole('supervisor'));
    $month = now()->format('Y-m');

    $this->post(route('environment.store'), ['site_id' => $this->site->id, 'month' => $month, 'metric' => 'water', 'value' => 610])->assertSessionHasNoErrors();
    $this->post(route('environment.store'), ['site_id' => $this->site->id, 'month' => $month, 'metric' => 'water', 'value' => 640])->assertSessionHasNoErrors();
    $this->post(route('environment.store'), ['site_id' => $this->site->id, 'month' => $month, 'metric' => SiteMetric::HOURS, 'value' => 1])->assertSessionHasErrors('metric');

    expect(SiteMetric::query()->sole()->value)->toBe('640.00');
});

test('the log totals each metric for the year, works out the recycling rate and counts spills', function () {
    $year = now()->year;
    foreach ([['general-waste', 30], ['hazardous-waste', 10], ['recycled-waste', 40]] as [$metric, $value]) {
        SiteMetric::create(['site_id' => $this->site->id, 'month' => "{$year}-01-01", 'metric' => $metric, 'value' => $value / 2]);
        SiteMetric::create(['site_id' => $this->site->id, 'month' => "{$year}-02-01", 'metric' => $metric, 'value' => $value / 2]);
    }
    SiteMetric::create(['site_id' => $this->site->id, 'month' => "{$year}-02-01", 'metric' => SiteMetric::HOURS, 'value' => 99_999]);
    Incident::create(['site_id' => $this->site->id, 'type' => 'environmental-release', 'title' => 'Diesel spill', 'description' => 'x', 'occurred_at' => "{$year}-02-10 10:00"]);

    $this->actingAs($this->userWithRole('auditor'))->get(route('environment.index', ['year' => $year]))
        ->assertInertia(fn (Assert $page) => $page->component('environment/index')
            ->has('metrics', 6)
            ->where('totals.general-waste', 30)
            ->where('totals.recycled-waste', 40)
            ->where('recyclingRate', 50)   // 40 / (30 + 10 + 40)
            ->where("spills.{$year}-02", 1));
});

test('workers do not see the environmental log; auditors cannot edit it', function () {
    $this->actingAs($this->userWithRole('worker'))->get(route('environment.index'))->assertForbidden();
    $this->actingAs($this->userWithRole('auditor'))
        ->post(route('environment.store'), ['site_id' => $this->site->id, 'month' => now()->format('Y-m'), 'metric' => 'water', 'value' => 1])
        ->assertForbidden();
});
