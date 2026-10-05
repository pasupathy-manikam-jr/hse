<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * A photo on a record, kept on the private `local` disk and served through an authorised route.
 *
 * @property int $id
 * @property string $photoable_type
 * @property int $photoable_id
 * @property string $path
 * @property string $sha256
 * @property int $size
 * @property CarbonImmutable $created_at
 */
class Photo extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleted(fn (self $photo) => Storage::disk('local')->delete($photo->path));
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function photoable(): MorphTo
    {
        return $this->morphTo();
    }
}
