<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\VisibleBySite;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

/**
 * A corrective action from any source (observation now; incidents, inspections, audits later).
 * Open → done (by its owner, with evidence) → verified (by someone else), or rejected back to open.
 *
 * @property int $id
 * @property string $number
 * @property string $source_type
 * @property int $source_id
 * @property int $site_id
 * @property string $description
 * @property string $control_level
 * @property string $priority
 * @property int $owner_id
 * @property CarbonImmutable $due_on
 * @property string $status
 * @property string|null $completion_notes
 * @property CarbonImmutable|null $done_at
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property string|null $rejection_reason
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Action extends Model
{
    use Auditable, HasCreator, VisibleBySite;

    /** The hierarchy of controls, most effective first (ISO 45001 §8.1.2). */
    public const CONTROL_LEVELS = ['elimination', 'substitution', 'engineering', 'administrative', 'ppe'];

    public const PRIORITIES = ['low', 'medium', 'high'];

    public const STATUSES = ['open', 'done', 'verified'];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'open' => ['done'],
        'done' => ['verified', 'open'],
    ];

    protected $guarded = ['id', 'number', 'status', 'completion_notes', 'done_at', 'verified_by', 'verified_at', 'rejection_reason', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (self $action) {
            $action->number = Sequence::next('ACT');
            $action->status = 'open';
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date:Y-m-d',
            'done_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', 'open')->whereDate('due_on', '<', today());
    }

    /**
     * Move the action on. "done" needs completion notes from the owner; "verified" needs
     * someone other than the owner; going back to "open" is a rejection and needs a reason.
     */
    public function transitionTo(string $status, User $by, ?string $notes = null): void
    {
        if (! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('A :from action cannot be marked :to.', ['from' => $this->status, 'to' => $status])]);
        }

        $error = match ($status) {
            'done' => match (true) {
                $by->id !== $this->owner_id => __('Only the action owner can mark it done.'),
                blank($notes) => __('Describe what was done.'),
                default => null,
            },
            'verified', 'open' => match (true) {
                $by->id === $this->owner_id => __('The owner cannot verify their own action.'),
                $status === 'open' && blank($notes) => __('Give a reason for rejecting it.'),
                default => null,
            },
        };

        if ($error) {
            throw ValidationException::withMessages(['notes' => $error]);
        }

        $this->forceFill(match ($status) {
            'done' => ['completion_notes' => $notes, 'done_at' => now(), 'rejection_reason' => null],
            'verified' => ['verified_by' => $by->id, 'verified_at' => now()],
            'open' => ['rejection_reason' => $notes, 'done_at' => null],
        })->forceFill(['status' => $status])->save();
    }
}
