<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lockout/tagout isolation point on a permit. The permit cannot close until every
 * isolation has been removed.
 *
 * @property int $id
 * @property int $permit_id
 * @property string $point
 * @property string $method
 * @property string|null $lock_no
 * @property int|null $isolated_by
 * @property CarbonImmutable $isolated_at
 * @property int|null $removed_by
 * @property CarbonImmutable|null $removed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PermitIsolation extends Model
{
    use Auditable;

    protected $guarded = ['id', 'permit_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'isolated_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function isolator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'isolated_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by')->withTrashed();
    }
}
