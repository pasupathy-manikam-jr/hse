<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasPhotos;
use App\Models\Concerns\RaisesActions;
use App\Models\Concerns\VisibleBySite;
use App\Models\Contracts\ActionSource;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * An incident: injury, illness, damage, release, fire... Its classification is derived from the
 * worst treatment among the people involved. Reported → under investigation → actions in progress → closed.
 *
 * @property int $id
 * @property string $number
 * @property int $site_id
 * @property int|null $area_id
 * @property int|null $observation_id
 * @property int|null $risk_assessment_id
 * @property string $type
 * @property string|null $dangerous_occurrence
 * @property string $title
 * @property string $description
 * @property string|null $immediate_actions
 * @property CarbonImmutable $occurred_at
 * @property string $classification
 * @property bool $recordable
 * @property string $status
 * @property string|null $investigation_team
 * @property string|null $sequence_of_events
 * @property list<string>|null $whys
 * @property string|null $root_cause_category
 * @property string|null $root_cause
 * @property string|null $contributing_factors
 * @property int|null $created_by
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $dosh_notified_at
 * @property int|null $dosh_notified_by
 * @property string|null $dosh_notice
 * @property CarbonImmutable|null $jkkp_submitted_at
 * @property int|null $jkkp_submitted_by
 * @property string|null $jkkp_reference
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Incident extends Model implements ActionSource
{
    use Auditable, HasCreator, HasPhotos, RaisesActions, VisibleBySite;

    public const TYPES = ['injury', 'illness', 'property-damage', 'environmental-release', 'vehicle', 'fire', 'security'];

    /** Least to most severe; the incident takes the worst one among its injured people. */
    public const CLASSIFICATIONS = ['no-injury', 'first-aid', 'medical', 'restricted', 'lost-time', 'fatality'];

    /** OSHA 29 CFR 1904.7: medical treatment beyond first aid, restricted work, days away or death. */
    public const RECORDABLE = ['medical', 'restricted', 'lost-time', 'fatality'];

    public const ROOT_CAUSE_CATEGORIES = ['procedures', 'training', 'equipment', 'supervision', 'work-environment', 'human-factors', 'design'];

    /**
     * Dangerous occurrences notifiable wherever they occur (NADOPOD 2004 Second Schedule; DOSH
     * guideline Table 6), by DOSH code. The mine, quarry and forestry lists are not included.
     *
     * PHP keeps these numeric codes as int keys; the column stores the code as a string.
     *
     * @var array<int, string>
     */
    public const DANGEROUS_OCCURRENCES = [
        '110' => 'Collapse of a scaffold over 5 m high',
        '210' => 'Collapse of a building or structure under construction, or of false-work',
        '310' => 'Electrical short circuit or overload with fire or explosion (plant stopped over 24 h)',
        '410' => 'Uncontrolled release or escape of a substance or agent',
        '510' => 'Explosion, fire or structural failure affecting a work room, machinery or plant',
        '520' => 'Explosion or fire from process materials (normal work stopped over 24 h)',
        '530' => 'Bursting of a revolving vessel, wheel or grinding wheel',
        '540' => 'Bursting, explosion or collapse of a pipe-line, or ignition in a pipe-line',
        '550' => 'Failure of a closed vessel: steam boiler or unfired pressure vessel',
        '560' => 'Fire or explosion where hazardous substances are stored',
        '610' => 'Collapse, overturning or failure of a crane, hoist, lift, piling frame or excavator',
    ];

    /** Days off work above which an injury must be reported on JKKP 6 (NADOPOD, "more than four calendar days"). */
    public const REPORTABLE_DAYS_LOST = 4;

    /** The written report is due within 7 working days; counted here as 7 calendar days, the safe side. */
    public const REPORT_DAYS = 7;

    public const STATUSES = ['reported', 'under-investigation', 'actions-in-progress', 'closed'];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'reported' => ['under-investigation', 'closed'],
        'under-investigation' => ['actions-in-progress', 'closed'],
        'actions-in-progress' => ['closed'],
    ];

    protected $guarded = ['id', 'number', 'status', 'classification', 'recordable', 'created_by', 'closed_by', 'closed_at',
        'dosh_notified_at', 'dosh_notified_by', 'dosh_notice', 'jkkp_submitted_at', 'jkkp_submitted_by', 'jkkp_reference'];

    protected static function booted(): void
    {
        static::creating(function (self $incident) {
            $incident->number = Sequence::next('INC');
            $incident->status = 'reported';
        });

        // Linking an incident to an assessment means the assessment did not prevent it: flag it for review.
        static::saved(function (self $incident) {
            if ($incident->wasChanged('risk_assessment_id') || ($incident->wasRecentlyCreated && $incident->risk_assessment_id)) {
                $incident->riskAssessment?->flagForReview(__(':number occurred under this assessment.', ['number' => $incident->number]));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'closed_at' => 'datetime',
            'dosh_notified_at' => 'datetime',
            'jkkp_submitted_at' => 'datetime',
            'recordable' => 'boolean',
            'whys' => 'array',
        ];
    }

    public function canBeViewedBy(User $user): bool
    {
        return $user->can('manage-incidents') && $this->isVisibleTo($user);
    }

    public function url(): string
    {
        return route('incidents.show', $this);
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * The observation (near miss) this incident was escalated from.
     *
     * @return BelongsTo<Observation, $this>
     */
    public function observation(): BelongsTo
    {
        return $this->belongsTo(Observation::class);
    }

    /**
     * The risk assessment that should have controlled this incident.
     *
     * @return BelongsTo<RiskAssessment, $this>
     */
    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(RiskAssessment::class);
    }

    /**
     * @return HasMany<IncidentPerson, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(IncidentPerson::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    /**
     * Re-derive the classification from the people involved (called whenever they change).
     */
    public function reclassify(): void
    {
        $worst = $this->people()->where('role', 'injured')->pluck('treatment')
            ->map(fn (?string $treatment) => array_search($treatment ?? 'no-injury', self::CLASSIFICATIONS, true))
            ->max() ?: 0;
        $classification = self::CLASSIFICATIONS[$worst];

        $this->forceFill([
            'classification' => $classification,
            'recordable' => in_array($classification, self::RECORDABLE, true),
        ])->save();
    }

    /**
     * What DOSH must be told under NADOPOD 2004, or null when nothing is notifiable:
     * - a death, a serious bodily injury or a dangerous occurrence: notify the nearest DOSH office
     *   immediately by the quickest means, then JKKP 6 within 7 working days;
     * - an injury keeping someone off work for more than 4 calendar days: JKKP 6 within 7 days;
     * - an occupational poisoning or disease: JKKP 7 within 7 days.
     *
     * @return array{form: string, immediate: bool, due_on: string, reasons: list<string>, notified: bool, submitted: bool, overdue: bool}|null
     */
    public function doshDuty(): ?array
    {
        $injured = $this->people->where('role', 'injured');
        $death = $injured->contains('treatment', 'fatality');
        $serious = $injured->contains('serious_injury', true);
        $reasons = [];

        if ($death) {
            $reasons[] = __('A death');
        }

        if ($serious) {
            $reasons[] = __('A serious bodily injury');
        }

        if ($this->dangerous_occurrence !== null) {
            $reasons[] = __('Dangerous occurrence :code', ['code' => $this->dangerous_occurrence]);
        }

        if ($injured->contains(fn (IncidentPerson $p) => $p->days_lost > self::REPORTABLE_DAYS_LOST)) {
            $reasons[] = __('More than :n days off work', ['n' => self::REPORTABLE_DAYS_LOST]);
        }

        if ($this->type === 'illness' && $injured->isNotEmpty()) {
            $reasons[] = __('An occupational poisoning or disease');
        }

        if ($reasons === []) {
            return null;
        }

        $dueOn = $this->occurred_at->startOfDay()->addDays(self::REPORT_DAYS);

        return [
            'form' => $this->type === 'illness' && $this->dangerous_occurrence === null && ! $death ? 'JKKP 7' : 'JKKP 6',
            'immediate' => $death || $serious || $this->dangerous_occurrence !== null,
            'due_on' => $dueOn->toDateString(),
            'reasons' => $reasons,
            'notified' => $this->dosh_notified_at !== null,
            'submitted' => $this->jkkp_submitted_at !== null,
            'overdue' => $this->jkkp_submitted_at === null && $dueOn->isPast() && ! $dueOn->isToday(),
        ];
    }

    /**
     * Incidents still owing DOSH their JKKP form, oldest first, from the given (scoped) query.
     *
     * @param  Builder<Incident>  $query
     * @return Collection<int, Incident>
     */
    public static function owingDosh(Builder $query): Collection
    {
        return $query->whereNull('jkkp_submitted_at')->where('status', '!=', 'closed')
            ->with('people')->orderBy('occurred_at')->get()
            ->filter(fn (Incident $incident) => $incident->doshDuty() !== null)->values();
    }

    /**
     * Record the immediate notice to DOSH: when, and how (phone to which office, email, reference).
     */
    public function recordDoshNotice(User $by, CarbonInterface $at, string $detail): void
    {
        $this->forceFill(['dosh_notified_at' => $at, 'dosh_notified_by' => $by->id, 'dosh_notice' => $detail])->save();
    }

    /**
     * Record that the JKKP 6 / JKKP 7 form went to DOSH, with its reference.
     */
    public function recordJkkpSubmitted(User $by, CarbonInterface $at, string $reference): void
    {
        $this->forceFill(['jkkp_submitted_at' => $at, 'jkkp_submitted_by' => $by->id, 'jkkp_reference' => $reference])->save();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doshNotifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dosh_notified_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function jkkpSubmitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jkkp_submitted_by')->withTrashed();
    }

    public function investigationComplete(): bool
    {
        return filled($this->root_cause_category) && filled($this->root_cause) && collect($this->whys)->filter()->isNotEmpty();
    }

    public function transitionTo(string $status): void
    {
        if (! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('A :from incident cannot be marked :to.', ['from' => $this->status, 'to' => $status])]);
        }

        $error = match ($status) {
            'actions-in-progress' => match (true) {
                ! $this->investigationComplete() => __('Complete the investigation first: at least one why, the root cause and its category.'),
                ! $this->actions()->exists() => __('Raise at least one corrective action first.'),
                default => null,
            },
            'closed' => match (true) {
                $this->recordable && ! $this->investigationComplete() => __('A recordable incident must be investigated before it is closed.'),
                $this->hasUnverifiedActions() => __('Every action must be verified before the incident is closed.'),
                ($duty = $this->doshDuty()) !== null && ! $duty['submitted'] => __('Record the :form sent to DOSH first.', ['form' => $duty['form']]),
                default => null,
            },
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['status' => $error]);
        }

        $this->forceFill(match ($status) {
            'closed' => ['closed_by' => auth()->id(), 'closed_at' => now()],
            default => [],
        })->forceFill(['status' => $status])->save();
    }
}
