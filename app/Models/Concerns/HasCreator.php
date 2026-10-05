<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Fills created_by with the signed-in user when a record is created.
 */
trait HasCreator
{
    protected static function bootHasCreator(): void
    {
        static::creating(function (self $model) {
            $model->created_by ??= Auth::user()?->id;
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
