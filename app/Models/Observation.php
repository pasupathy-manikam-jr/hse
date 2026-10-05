<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPhotos;
use App\Models\Concerns\RaisesActions;
use App\Models\Concerns\VisibleBySite;
use App\Models\Contracts\ActionSource;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

/**
 * An observation from the field: unsafe act or condition, near miss, good practice or
 * environmental issue. Open → actioned (an action was raised) → closed.
 *
 * @property int $id
 * @property string $number
 * @property string|null $client_ref
 * @property int $site_id
 * @property int|null $area_id
 * @property string $type
 * @property string $potential
 * @property string $description
 * @property string|null $immediate_action
 * @property CarbonImmutable $observed_at
 * @property bool $anonymous
 * @property int|null $reporter_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $status
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Observation extends Model implements ActionSource
{
    use Auditable, HasPhotos, RaisesActions, VisibleBySite;

    public const TYPES = ['unsafe-act', 'unsafe-condition', 'near-miss', 'positive', 'environmental'];

    public const POTENTIALS = ['low', 'medium', 'high'];

    public const STATUSES = ['open', 'actioned', 'closed'];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'open' => ['actioned', 'closed'],
        'actioned' => ['closed'],
    ];

    protected $guarded = ['id', 'number', 'status', 'closed_by', 'closed_at'];

    protected static function booted(): void
    {
        static::creating(function (self $observation) {
            $observation->number = Sequence::next('OBS');
            $observation->status = 'open';
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'closed_at' => 'datetime',
            'anonymous' => 'boolean',
        ];
    }

    public function canBeViewedBy(User $user): bool
    {
        return $user->can('manage-observations') && $this->isVisibleTo($user);
    }

    /**
     * An anonymous report must not record who sent it, in the audit trail either.
     */
    protected function hidesAuditActor(string $event): bool
    {
        return $event === 'created' && $this->anonymous;
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
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    /**
     * The incident this observation was escalated to, if any.
     *
     * @return HasOne<Incident, $this>
     */
    public function incident(): HasOne
    {
        return $this->hasOne(Incident::class);
    }

    public function url(): string
    {
        return route('observations.show', $this);
    }

    protected function actionRaised(): void
    {
        if ($this->status === 'open') {
            $this->transitionTo('actioned');
        }
    }

    public function transitionTo(string $status): void
    {
        if (! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('An :from observation cannot be marked :to.', ['from' => $this->status, 'to' => $status])]);
        }

        if ($status === 'closed' && $this->hasUnverifiedActions()) {
            throw ValidationException::withMessages(['status' => __('Every action must be verified before the observation is closed.')]);
        }

        $this->forceFill(match ($status) {
            'closed' => ['closed_by' => auth()->id(), 'closed_at' => now()],
            default => [],
        })->forceFill(['status' => $status])->save();
    }
}
