<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\VisibleBySite;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * A shift handover at a site. The open permits and the isolations still in place are copied in
 * when it is written, so it records what the incoming supervisor was told, even after permits move on.
 *
 * @property int $id
 * @property string $number
 * @property int $site_id
 * @property string $shift
 * @property int $to_user_id
 * @property string $notes
 * @property list<array<string, mixed>> $open_permits
 * @property CarbonImmutable|null $acknowledged_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ShiftHandover extends Model
{
    use Auditable, HasCreator, VisibleBySite;

    public const SHIFTS = ['day', 'night'];

    protected $guarded = ['id', 'number', 'open_permits', 'acknowledged_at', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (self $handover) {
            $handover->number = Sequence::next('SHO');
            $handover->open_permits = $handover->snapshot();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'open_permits' => 'array',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id')->withTrashed();
    }

    /**
     * The incoming supervisor confirms they have read the handover.
     */
    public function acknowledge(User $by): void
    {
        if ($by->id !== $this->to_user_id) {
            throw ValidationException::withMessages(['status' => __('Only the person taking over can acknowledge the handover.')]);
        }

        if ($this->acknowledged_at !== null) {
            throw ValidationException::withMessages(['status' => __('This handover is already acknowledged.')]);
        }

        $this->forceFill(['acknowledged_at' => now()])->save();
    }

    /**
     * Permits still open at the site, each with its isolations still in place.
     *
     * @return list<array<string, mixed>>
     */
    private function snapshot(): array
    {
        return array_values(Permit::query()->where('site_id', $this->site_id)->whereIn('status', ['approved', 'active', 'suspended'])
            ->with('area:id,name', 'isolations')->orderBy('number')->get()
            ->map(fn (Permit $p) => [
                'id' => $p->id,
                'number' => $p->number,
                'type' => $p->type,
                'status' => $p->status,
                'area' => $p->area?->name,
                'valid_to' => $p->valid_to->toIso8601String(),
                'isolations' => array_values($p->isolations->whereNull('removed_at')
                    ->map(fn (PermitIsolation $i) => ['point' => $i->point, 'lock_no' => $i->lock_no])->all()),
            ])->all());
    }
}
