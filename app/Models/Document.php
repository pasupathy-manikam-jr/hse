<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

/**
 * A controlled document (ISO 45001 §7.5): policy, safe work procedure, emergency plan...
 * Its content lives in revisions; exactly one revision is effective once the first is approved.
 *
 * @property int $id
 * @property string $number
 * @property string $title
 * @property string $type
 * @property int|null $owner_id
 * @property int $review_interval_months
 * @property CarbonImmutable|null $next_review_on
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Document extends Model
{
    use Auditable, HasCreator;

    public const TYPES = ['policy', 'procedure', 'work-instruction', 'emergency-plan', 'form', 'record'];

    /** A review is "due" this many days ahead. */
    public const REVIEW_WINDOW_DAYS = 30;

    protected $guarded = ['id', 'created_by', 'next_review_on'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review_interval_months' => 'integer',
            'next_review_on' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    /**
     * @return HasMany<DocumentRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class)->orderByDesc('id');
    }

    /**
     * @return HasOne<DocumentRevision, $this>
     */
    public function effectiveRevision(): HasOne
    {
        return $this->hasOne(DocumentRevision::class)->where('status', 'effective');
    }

    /**
     * @return BelongsToMany<IsoClause, $this>
     */
    public function clauses(): BelongsToMany
    {
        return $this->belongsToMany(IsoClause::class)->orderBy('number');
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeDueForReview(Builder $query): void
    {
        $query->whereNotNull('next_review_on')->whereDate('next_review_on', '<=', today()->addDays(self::REVIEW_WINDOW_DAYS));
    }

    /**
     * Start the next revision (A, B, C...) as a draft. Only one revision is in the works at a time.
     */
    public function startRevision(): DocumentRevision
    {
        if ($this->revisions()->whereIn('status', ['draft', 'in-review'])->exists()) {
            throw ValidationException::withMessages(['revision' => __('A revision of this document is already being worked on.')]);
        }

        $latest = $this->revisions()->value('revision');

        return $this->revisions()->create(['revision' => $latest === null ? 'A' : str_increment($latest)]);
    }

    /**
     * A periodic review that found nothing to change: the next review moves on one interval.
     */
    public function confirmReview(): void
    {
        if (! $this->effectiveRevision()->exists()) {
            throw ValidationException::withMessages(['review' => __('Only a document with an effective revision can be reviewed.')]);
        }

        $this->forceFill(['next_review_on' => CarbonImmutable::today()->addMonths($this->review_interval_months)])->save();
        $this->audit('reviewed', null, ['next_review_on' => $this->next_review_on?->toDateString()]);
    }
}
