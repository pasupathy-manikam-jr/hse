<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\VisibleBySite;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A risk assessment (HIRA, JSA, ...) for an activity at a site. Draft → approved → superseded;
 * a new revision copies the hazards into a fresh draft under the same number.
 *
 * @property int $id
 * @property string $number
 * @property int $revision
 * @property int|null $previous_id
 * @property int $site_id
 * @property int|null $area_id
 * @property string $type
 * @property string $title
 * @property string $activity
 * @property CarbonImmutable $review_due_on
 * @property string $status
 * @property bool $review_required
 * @property string|null $review_reason
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class RiskAssessment extends Model
{
    use Auditable, HasCreator, VisibleBySite;

    public const TYPES = ['hira', 'jsa', 'coshh', 'manual-handling'];

    public const STATUSES = ['draft', 'approved', 'superseded'];

    /** A hazard whose residual score reaches this cannot be accepted: more controls are needed. */
    public const RESIDUAL_LIMIT = 15;

    /** In memory from creation, as in the database: revise() reads it. */
    protected $attributes = ['revision' => 1];

    protected $guarded = ['id', 'number', 'revision', 'previous_id', 'status', 'review_required', 'review_reason', 'approved_by', 'approved_at', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (self $assessment) {
            $assessment->number ??= Sequence::next('RA');
            $assessment->status = 'draft';
        });
    }

    /**
     * Score bands on the 5×5 matrix (likelihood × severity).
     */
    public static function band(int $score): string
    {
        return match (true) {
            $score >= self::RESIDUAL_LIMIT => 'extreme',
            $score >= 10 => 'high',
            $score >= 5 => 'medium',
            default => 'low',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review_due_on' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'review_required' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RiskHazard, $this>
     */
    public function hazards(): HasMany
    {
        return $this->hasMany(RiskHazard::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    /**
     * Incidents that this assessment should have controlled.
     *
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function isReviewOverdue(): bool
    {
        return $this->status === 'approved' && $this->review_due_on->isPast();
    }

    /**
     * Approve this draft: someone other than its author, at least one hazard, and every
     * residual score below the limit. The previously approved revision is superseded.
     */
    public function approve(User $by): void
    {
        $error = match (true) {
            $this->status !== 'draft' => __('Only a draft can be approved.'),
            $by->id === $this->created_by => __('The author cannot approve their own assessment.'),
            ! $this->hazards()->exists() => __('Add at least one hazard first.'),
            $this->hazards->contains(fn (RiskHazard $h) => $h->residual_score >= self::RESIDUAL_LIMIT) => __('A hazard is still at :limit or above after controls. Add controls until it is lower.', ['limit' => self::RESIDUAL_LIMIT]),
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['status' => $error]);
        }

        DB::transaction(function () use ($by) {
            self::query()->where('number', $this->number)->where('status', 'approved')
                ->get()->each(fn (self $old) => $old->forceFill(['status' => 'superseded'])->save());

            // A fresh approval answers any review request against this assessment.
            $this->forceFill(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now(), 'review_required' => false, 'review_reason' => null])->save();
        });
    }

    /**
     * Start the next revision: a draft copy of this approved assessment and its hazards.
     */
    public function revise(): self
    {
        if ($this->status !== 'approved') {
            throw ValidationException::withMessages(['status' => __('Only the approved revision can be revised.')]);
        }

        if (self::query()->where('number', $this->number)->where('status', 'draft')->exists()) {
            throw ValidationException::withMessages(['status' => __('A draft revision already exists.')]);
        }

        return DB::transaction(function () {
            $draft = $this->replicate(['status', 'approved_by', 'approved_at', 'created_by', 'review_required', 'review_reason']);
            $draft->forceFill(['revision' => $this->revision + 1, 'previous_id' => $this->id, 'created_by' => null])->save();

            foreach ($this->hazards as $hazard) {
                $draft->hazards()->create($hazard->only(['hazard', 'who_at_risk', 'existing_controls', 'likelihood', 'severity', 'additional_controls', 'residual_likelihood', 'residual_severity']));
            }

            return $draft;
        });
    }

    public function flagForReview(string $reason): void
    {
        $this->forceFill(['review_required' => true, 'review_reason' => $reason])->save();
    }
}
