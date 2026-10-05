<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workplace (project site, plant, depot). Every HSE record belongs to one.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property bool $active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Site extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Active sites a user may work on: their home site, or every site.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAvailableTo(Builder $query, User $user): void
    {
        $query->where('active', true)
            ->when($user->site_id !== null, fn (Builder $q) => $q->whereKey($user->site_id))
            ->orderBy('code');
    }

    /**
     * @return HasMany<Area, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class)->orderBy('name');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
