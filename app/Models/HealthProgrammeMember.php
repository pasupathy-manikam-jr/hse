<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A worker exposed under a health programme, from the day the exposure began until it ended.
 *
 * @property int $id
 * @property int $health_programme_id
 * @property int $user_id
 * @property CarbonImmutable $started_on
 * @property CarbonImmutable|null $ended_on
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class HealthProgrammeMember extends Model
{
    use Auditable;

    protected $guarded = ['id', 'health_programme_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_on' => 'date:Y-m-d',
            'ended_on' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<HealthProgramme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(HealthProgramme::class, 'health_programme_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
