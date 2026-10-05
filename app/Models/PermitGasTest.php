<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One atmosphere test before or during confined-space entry. Passes only when every reading is
 * inside Permit::OXYGEN_RANGE and below Permit::GAS_BELOW.
 *
 * @property int $id
 * @property int $permit_id
 * @property string $oxygen
 * @property string $lel
 * @property string $h2s
 * @property string $co
 * @property bool $passed
 * @property int|null $tested_by
 * @property CarbonImmutable $tested_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PermitGasTest extends Model
{
    use Auditable;

    protected $guarded = ['id', 'permit_id', 'passed'];

    protected static function booted(): void
    {
        static::saving(function (self $test): void {
            [$minOxygen, $maxOxygen] = Permit::OXYGEN_RANGE;
            $test->passed = (float) $test->oxygen >= $minOxygen && (float) $test->oxygen <= $maxOxygen
                && collect(Permit::GAS_BELOW)->every(fn (float $limit, string $gas) => (float) $test->{$gas} < $limit);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'tested_at' => 'datetime',
            'oxygen' => 'decimal:2',
            'lel' => 'decimal:2',
            'h2s' => 'decimal:2',
            'co' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Permit, $this>
     */
    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by')->withTrashed();
    }
}
