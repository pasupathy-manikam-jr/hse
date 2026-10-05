<?php

namespace App\Models\Concerns;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that belong to a site. A user with a home site sees only that site's records;
 * a user without one (HSE manager, admin) sees every site.
 */
trait VisibleBySite
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->when($user->site_id !== null, fn (Builder $q) => $q->where($this->qualifyColumn('site_id'), $user->site_id));
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->site_id === null || $user->site_id === $this->site_id;
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
