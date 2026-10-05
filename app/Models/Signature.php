<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * An electronic signature: the signer (and their name at the time), the record, the meaning
 * ("approved", "verified", ...) and the time. Append-only, like the audit trail.
 *
 * @property int $id
 * @property int $user_id
 * @property string $signable_type
 * @property int $signable_id
 * @property string $meaning
 * @property string $signer_name
 * @property string|null $ip_address
 * @property CarbonImmutable $signed_at
 */
class Signature extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Signatures cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Signatures cannot be deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function signable(): MorphTo
    {
        return $this->morphTo();
    }
}
