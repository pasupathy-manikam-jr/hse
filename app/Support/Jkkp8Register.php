<?php

namespace App\Support;

use App\Models\Incident;
use App\Models\IncidentPerson;
use App\Models\SiteMetric;
use Illuminate\Support\Collection;

/**
 * The JKKP 8 register (NADOPOD 2004) for one site and calendar year: accidents, dangerous
 * occurrences and occupational poisonings/diseases, and the annual summary for DOSH HQ.
 *
 * Accident and disease cases are the injured people needing medical treatment or worse (first
 * aid is not recordable, DOSH guideline 2.13). A death counts as 6,000 lost days (guideline 2.11).
 * Rates: incident and fatality per 1,000 workers (the year's average of the monthly "average
 * workers" figure), frequency and severity per 1,000,000 man-hours (the year's hours worked).
 */
class Jkkp8Register
{
    /** DOSH guideline 2.11: a fatal case counts as this many lost workdays. */
    public const DAYS_PER_DEATH = 6000;

    public function __construct(private int $siteId, private int $year) {}

    /**
     * @return array{accidents: list<array<string, mixed>>, occurrences: list<array<string, mixed>>, diseases: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function data(): array
    {
        $incidents = Incident::query()->where('site_id', $this->siteId)->whereYear('occurred_at', $this->year)
            ->with('people', 'area:id,name')->orderBy('occurred_at')->get();
        $cases = $incidents->flatMap(fn (Incident $incident) => $incident->people
            ->filter(fn (IncidentPerson $p) => $p->role === 'injured' && in_array($p->treatment, Incident::RECORDABLE, true))
            ->map(fn (IncidentPerson $p) => [
                'incident_id' => $incident->id,
                'number' => $incident->number,
                'date' => $incident->occurred_at->toDateString(),
                // As on the OSHA log, a privacy case is listed without the name.
                'name' => $p->privacy_case ? __('Privacy case') : $p->name,
                'job_title' => $p->job_title,
                'where' => $incident->area->name ?? '',
                'description' => collect([$p->injury_nature, $p->body_part, $incident->title])->filter()->implode(' — '),
                'death' => $p->treatment === 'fatality',
                'serious' => $p->serious_injury,
                'days_lost' => $p->treatment === 'fatality' ? self::DAYS_PER_DEATH : $p->days_lost,
                'lost_workday_case' => $p->treatment === 'fatality' || $p->days_lost > 0 || $p->days_restricted > 0,
                'disease' => $incident->type === 'illness',
                'illness_type' => $p->illness_type,
                'reported' => $incident->jkkp_submitted_at !== null,
            ]))->values();

        [$diseases, $accidents] = $cases->partition(fn (array $c) => $c['disease']);
        $hours = $this->metric(SiteMetric::HOURS);
        $workers = $this->metric(SiteMetric::WORKERS);
        $averageWorkers = $workers->isEmpty() ? null : $workers->avg();
        $manHours = $hours->isEmpty() ? null : (float) $hours->sum();

        return [
            'accidents' => array_values($accidents->all()),
            'occurrences' => array_values($incidents->whereNotNull('dangerous_occurrence')->map(fn (Incident $i) => [
                'incident_id' => $i->id, 'number' => $i->number, 'date' => $i->occurred_at->toDateString(), 'code' => $i->dangerous_occurrence,
                'title' => $i->title, 'reported' => $i->jkkp_submitted_at !== null,
            ])->values()->all()),
            'diseases' => array_values($diseases->all()),
            'summary' => [
                'accident_cases' => $accidents->count(),
                'accident_deaths' => $accidents->where('death', true)->count(),
                'accident_lost_workday_cases' => $accidents->where('lost_workday_case', true)->count(),
                'accident_days_lost' => (int) $accidents->sum('days_lost'),
                'disease_cases' => $diseases->count(),
                'disease_deaths' => $diseases->where('death', true)->count(),
                'disease_days_lost' => (int) $diseases->sum('days_lost'),
                'dangerous_occurrences' => $incidents->whereNotNull('dangerous_occurrence')->count(),
                'man_hours' => $manHours,
                'average_workers' => $averageWorkers === null ? null : round((float) $averageWorkers, 1),
                'months_with_hours' => $hours->count(),
                'incident_rate' => $this->rate($accidents->count(), 1_000, $averageWorkers),
                'fatality_rate' => $this->rate($accidents->where('death', true)->count(), 1_000, $averageWorkers),
                'frequency_rate' => $this->rate($accidents->count(), 1_000_000, $manHours),
                'severity_rate' => $this->rate((int) $accidents->sum('days_lost'), 1_000_000, $manHours),
                'due_on' => ($this->year + 1).'-01-31',
            ],
        ];
    }

    /**
     * @return Collection<int, float>
     */
    private function metric(string $metric): Collection
    {
        return SiteMetric::query()->where('site_id', $this->siteId)->where('metric', $metric)
            ->whereYear('month', $this->year)->pluck('value')->map(fn ($v) => (float) $v);
    }

    private function rate(int $count, int $base, float|int|null $denominator): ?float
    {
        return $denominator ? round($count * $base / $denominator, 2) : null;
    }
}
