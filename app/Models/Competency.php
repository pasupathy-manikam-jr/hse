<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A training or ticket people can hold ("Confined space entry", valid 24 months). One tied
 * to a permit type is required of every worker named on a permit of that type.
 *
 * @property int $id
 * @property string $name
 * @property int|null $validity_months
 * @property string|null $permit_type
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Competency extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    /**
     * @return HasMany<UserCompetency, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(UserCompetency::class);
    }
}
