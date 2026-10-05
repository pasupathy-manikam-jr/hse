<?php

namespace App\Models\Concerns;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

/**
 * Records that carry site photos (observations, incidents, inspection answers). Phones send
 * JPEG for <input accept="image/*">, so HEIC is not accepted.
 */
trait HasPhotos
{
    public const PHOTO_MAX = 5;

    public const PHOTO_MAX_KB = 10240;

    /**
     * Who may open this record, and so its photos (module permission and site).
     */
    abstract public function canBeViewedBy(User $user): bool;

    protected static function bootHasPhotos(): void
    {
        static::deleting(fn (self $model) => $model->photos()->get()->each->delete());
    }

    /**
     * Validation rules for a `photos` upload field.
     *
     * @return array<string, list<string>>
     */
    public static function photoRules(): array
    {
        return [
            'photos' => ['nullable', 'array', 'max:'.self::PHOTO_MAX],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::PHOTO_MAX_KB],
        ];
    }

    /**
     * @return MorphMany<Photo, $this>
     */
    public function photos(): MorphMany
    {
        return $this->morphMany(Photo::class, 'photoable')->orderBy('id');
    }

    /**
     * Store each file privately with its SHA-256, so the stored copy can be shown to be the one received.
     * The browser has already shrunk phone photos (PhotoPicker), so they are stored as sent.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function attachPhotos(array $files): void
    {
        foreach ($files as $file) {
            $this->photos()->create([
                'path' => $file->store('photos/'.now()->format('Y/m'), 'local'),
                'sha256' => hash_file('sha256', (string) $file->getRealPath()),
                'size' => $file->getSize(),
            ]);
        }
    }
}
