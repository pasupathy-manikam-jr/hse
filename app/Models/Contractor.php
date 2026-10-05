<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A contractor company. Its workers are users with this contractor_id. Only an approved
 * contractor with current insurance may work (checked when permits arrive in phase 4).
 *
 * @property int $id
 * @property string $name
 * @property string|null $registration_no
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property CarbonImmutable|null $insurance_expires_on
 * @property bool $approved
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Contractor extends Model
{
    use Auditable, HasCreator;

    protected $guarded = ['id', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'insurance_expires_on' => 'date:Y-m-d',
            'approved' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function workers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function canWork(): bool
    {
        // Insurance is valid through the whole of its expiry day.
        return $this->approved && $this->insurance_expires_on?->endOfDay()->isFuture() === true;
    }
}
