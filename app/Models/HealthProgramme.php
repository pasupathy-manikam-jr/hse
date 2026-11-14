<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\VisibleBySite;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A health surveillance programme for one exposure at one site: the exposed workers, their
 * examinations and the exposure monitoring.
 * - Noise (Noise Exposure Regulations 2019 + DOSH Industry Code of Practice 2019): baseline
 *   audiogram within 3 months of starting noisy work, then every year; a temporary threshold
 *   shift is retested within 3 months; the noise risk assessment is redone within 5 years.
 * - Chemical (USECHH Regulations 2000): for a Schedule II chemical, medical surveillance by an
 *   occupational health doctor at least every 12 months (reg. 27(3)) and exposure monitoring at
 *   least every 6 months (reg. 26(2)); records kept 30 years (reg. 27(4)).
 *
 * @property int $id
 * @property int $site_id
 * @property string $name
 * @property string $hazard
 * @property int|null $chemical_id
 * @property bool $schedule_ii
 * @property int $exam_interval_months
 * @property int|null $monitoring_interval_months
 * @property string|null $exposure_limit
 * @property string|null $unit
 * @property bool $active
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class HealthProgramme extends Model
{
    use Auditable, HasCreator, VisibleBySite;

    public const HAZARDS = ['noise', 'chemical'];

    /** Noise: the baseline audiogram is due within this many months of starting noisy work. */
    public const BASELINE_MONTHS = 3;

    /** Noise: a temporary threshold shift is retested within this many months. */
    public const RETEST_MONTHS = 3;

    /** Exams and monitoring due within this many days are flagged, and reminded. */
    public const DUE_SOON_DAYS = 30;

    protected $guarded = ['id', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule_ii' => 'boolean',
            'active' => 'boolean',
            'exam_interval_months' => 'integer',
            'monitoring_interval_months' => 'integer',
            'exposure_limit' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Chemical, $this>
     */
    public function chemical(): BelongsTo
    {
        return $this->belongsTo(Chemical::class);
    }

    /**
     * @return HasMany<HealthProgrammeMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(HealthProgrammeMember::class);
    }

    /**
     * @return HasMany<HealthExam, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(HealthExam::class)->orderByDesc('examined_on')->orderByDesc('id');
    }

    /**
     * @return HasMany<ExposureReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(ExposureReading::class)->orderByDesc('measured_on')->orderByDesc('id');
    }

    /**
     * The outcomes an examination can have for this hazard.
     *
     * @return list<string>
     */
    public function outcomes(): array
    {
        return HealthExam::OUTCOMES[$this->hazard];
    }

    /**
     * Each current member's next examination: the type due, the date, and its state
     * (overdue, due-soon, ok; removed when the doctor found them unfit for the exposure).
     *
     * @return list<array{member_id: int, user_id: int, name: string, started_on: string, latest: HealthExam|null, next_type: string|null, due_on: string|null, state: string}>
     */
    public function schedule(): array
    {
        $exams = $this->exams()->get()->groupBy('user_id');

        return array_values($this->members()->whereNull('ended_on')->with('user:id,name')->get()
            ->sortBy(fn (HealthProgrammeMember $m) => $m->user->name)
            ->map(function (HealthProgrammeMember $member) use ($exams) {
                /** @var HealthExam|null $latest */
                $latest = $exams->get($member->user_id)?->first();
                [$type, $due] = match (true) {
                    $latest === null => ['baseline', $this->hazard === 'noise' ? $member->started_on->addMonthsNoOverflow(self::BASELINE_MONTHS) : $member->started_on],
                    $latest->outcome === 'unfit' => [null, null],
                    $latest->outcome === 'temporary-sts' => ['retest', $latest->examined_on->addMonthsNoOverflow(self::RETEST_MONTHS)],
                    default => ['periodic', $latest->examined_on->addMonthsNoOverflow($this->exam_interval_months)],
                };

                return [
                    'member_id' => $member->id,
                    'user_id' => $member->user_id,
                    'name' => $member->user->name,
                    'started_on' => $member->started_on->toDateString(),
                    'latest' => $latest,
                    'next_type' => $type,
                    'due_on' => $due?->toDateString(),
                    'state' => self::state($due, $latest?->outcome === 'unfit'),
                ];
            })->all());
    }

    /**
     * When exposure monitoring is next due (null when this programme has no interval set).
     */
    public function monitoringDueOn(): ?CarbonImmutable
    {
        if ($this->monitoring_interval_months === null) {
            return null;
        }

        $last = $this->readings()->value('measured_on');

        return $last === null ? $this->created_at?->startOfDay() : CarbonImmutable::parse($last)->addMonthsNoOverflow($this->monitoring_interval_months);
    }

    /**
     * What needs doing across these programmes now: examinations overdue or due within 30 days,
     * monitoring due, results to tell the worker (21 days) and to notify to DOSH (7 days), as lines.
     *
     * @param  Builder<HealthProgramme>  $query
     * @return list<string>
     */
    public static function todo(Builder $query): array
    {
        $lines = [];

        foreach ($query->where('active', true)->with('site:id,code')->get() as $programme) {
            foreach ($programme->schedule() as $row) {
                if (in_array($row['state'], ['overdue', 'due-soon'], true)) {
                    $lines[] = __(':programme: :name, :type due :d', ['programme' => $programme->name, 'name' => $row['name'], 'type' => __($row['next_type'] ?? ''), 'd' => $row['due_on']]);
                }
            }

            $monitoring = $programme->monitoringDueOn();

            if ($monitoring !== null && $monitoring->lte(today()->addDays(self::DUE_SOON_DAYS))) {
                $lines[] = __(':programme: exposure monitoring due :d', ['programme' => $programme->name, 'd' => $monitoring->toDateString()]);
            }

            foreach ($programme->exams()->with('user:id,name')->where(fn ($q) => $q->whereNull('informed_on')->orWhereNull('dosh_notified_on'))->get() as $exam) {
                if ($exam->informed_on === null) {
                    $lines[] = __(':programme: tell :name their :d result (within :n days)', ['programme' => $programme->name, 'name' => $exam->user->name, 'd' => $exam->examined_on->toDateString(), 'n' => HealthExam::INFORM_DAYS]);
                }

                if ($exam->notify_dosh && $exam->dosh_notified_on === null) {
                    $lines[] = __(':programme: notify DOSH of :name\'s :outcome (within :n days)', ['programme' => $programme->name, 'name' => $exam->user->name, 'outcome' => __(HealthExam::OUTCOME_LABELS[$exam->outcome] ?? $exam->outcome), 'n' => HealthExam::NOTIFY_DAYS]);
                }
            }
        }

        return $lines;
    }

    public static function state(?CarbonImmutable $due, bool $removed = false): string
    {
        return match (true) {
            $removed => 'removed',
            $due === null => 'ok',
            $due->lt(today()) => 'overdue',
            $due->lte(today()->addDays(self::DUE_SOON_DAYS)) => 'due-soon',
            default => 'ok',
        };
    }
}
