<?php

use App\Models\Equipment;
use App\Models\Incident;
use App\Models\Observation;
use App\Models\Site;
use App\Models\User;
use App\Notifications\WeeklySummary;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->other = Site::create(['code' => 'SA', 'name' => 'Plant']);
});

test('the weekly report covers Monday to Sunday of the chosen week, for the chosen site', function () {
    // Week 46 of 2026 runs from Monday 9 to Sunday 15 November.
    $nearMiss = Observation::create(['site_id' => $this->site->id, 'type' => 'near-miss', 'potential' => 'high', 'description' => 'x', 'observed_at' => '2026-11-09 07:00']);
    Observation::create(['site_id' => $this->site->id, 'type' => 'unsafe-act', 'potential' => 'low', 'description' => 'x', 'observed_at' => '2026-11-15 18:00']);
    Observation::create(['site_id' => $this->site->id, 'type' => 'near-miss', 'potential' => 'low', 'description' => 'x', 'observed_at' => '2026-11-16 08:00']);
    Observation::create(['site_id' => $this->other->id, 'type' => 'near-miss', 'potential' => 'low', 'description' => 'x', 'observed_at' => '2026-11-10 08:00']);
    Incident::create(['site_id' => $this->site->id, 'type' => 'injury', 'title' => 'Cut hand', 'description' => 'x', 'occurred_at' => '2026-11-11 10:00']);
    $overdue = $nearMiss->raiseAction(['description' => 'Fix rail', 'control_level' => 'engineering', 'priority' => 'high',
        'owner_id' => User::factory()->create()->id, 'due_on' => today()->subDays(2)]);
    Equipment::create(['site_id' => $this->site->id, 'asset_tag' => 'CR-01', 'name' => 'Crane', 'type' => 'lifting', 'inspection_interval_months' => 12]);

    $this->actingAs($this->userWithRole('hse-manager'))
        ->get(route('reports.weekly', ['week' => '2026-11-12', 'site_id' => $this->site->id]))
        ->assertInertia(fn (Assert $page) => $page->component('reports/weekly')
            ->where('week', ['from' => '2026-11-09', 'to' => '2026-11-15', 'number' => 46, 'year' => 2026])
            ->where('observations', ['near-miss' => 1, 'unsafe-act' => 1])
            ->where('incidents.0.title', 'Cut hand')
            ->where('actions.overdue.0.number', $overdue->number)
            ->where('equipmentOverdue.0.asset_tag', 'CR-01'));
});

test('a site user sees only their site, a bad week falls back to this week, and workers cannot open it', function () {
    Observation::create(['site_id' => $this->other->id, 'type' => 'near-miss', 'potential' => 'low', 'description' => 'x', 'observed_at' => now()]);
    $supervisor = $this->userWithRole('supervisor');
    $supervisor->update(['site_id' => $this->site->id]);

    $this->actingAs($supervisor)->get(route('reports.weekly', ['week' => 'soon', 'site_id' => $this->other->id]))
        ->assertInertia(fn (Assert $page) => $page->where('week.from', today()->startOfWeek()->toDateString())
            ->where('observations', [])->has('sites', 1));

    $this->actingAs($this->userWithRole('worker'))->get(route('reports.weekly'))->assertForbidden();
});

test('each Monday the sites with recipients get last week\'s summary by email', function () {
    Notification::fake();
    $this->site->update(['report_recipients' => ['pm@example.com', 'hse@example.com']]);
    Observation::create(['site_id' => $this->site->id, 'type' => 'near-miss', 'topic' => 'lifting', 'potential' => 'low', 'description' => 'x', 'observed_at' => now()->subWeek()]);

    $this->artisan('hse:weekly-report')->assertSuccessful();

    Notification::assertSentOnDemand(WeeklySummary::class, fn (WeeklySummary $n, array $channels, object $notifiable) => $n->site->is($this->site)
        && $notifiable->routes['mail'] === ['pm@example.com', 'hse@example.com']
        && $n->report['week']['from'] === today()->subWeek()->startOfWeek()->toDateString()
        && $n->report['topics']->all() === ['lifting' => 1]);
    Notification::assertCount(1);
});

test('report recipients are a list of valid addresses on the site', function () {
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->put(route('sites.update', $this->site), ['code' => 'KL', 'name' => 'Tower', 'report_recipients' => ['pm@example.com', 'not-an-email']])
        ->assertSessionHasErrors('report_recipients.1');
    $this->put(route('sites.update', $this->site), ['code' => 'KL', 'name' => 'Tower', 'report_recipients' => ['pm@example.com']])->assertSessionHasNoErrors();

    expect($this->site->fresh()->report_recipients)->toBe(['pm@example.com']);
});
