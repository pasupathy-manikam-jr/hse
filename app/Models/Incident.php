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

    public const STATUSES = ['reported', 'under-investigation', 'actions-in-progress', 'closed'];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'reported' => ['under-investigation', 'closed'],
        'under-investigation' => ['actions-in-progress', 'closed'],
        'actions-in-progress' => ['closed'],
    ];

    protected $guarded = ['id', 'number', 'status', 'classification', 'recordable', 'created_by', 'closed_by', 'closed_at'];

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
