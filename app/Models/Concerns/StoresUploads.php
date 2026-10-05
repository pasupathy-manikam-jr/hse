<?php

namespace App\Models\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One private file per record, kept on the `local` disk (never publicly served) in the
 * file_path / file_name / file_type / file_size / file_sha256 columns. The using model
 * defines UPLOAD_DIRECTORY. Serve it through an authorised route with downloadUpload().
 */
trait StoresUploads
{
    /** Scans and PDFs, as suppliers send them. */
    public const UPLOAD_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public const UPLOAD_MAX_KB = 10240;

    /**
     * Allowed file types; a model overrides this when it takes others.
     *
     * @return list<string>
     */
    public static function uploadExtensions(): array
    {
        return self::UPLOAD_EXTENSIONS;
    }

    protected static function bootStoresUploads(): void
    {
        static::deleted(fn (self $model) => $model->deleteUpload());
    }

    /**
     * Validation rules for the upload field.
     *
     * @return list<string>
     */
    public static function uploadRules(bool $required = false): array
    {
        $types = implode(',', static::uploadExtensions());

        return [$required ? 'required' : 'nullable', 'file', "mimes:{$types}", "extensions:{$types}", 'max:'.self::UPLOAD_MAX_KB];
    }

    /**
     * Store the file (replacing any previous one) and fill the file columns; the caller saves.
     * The SHA-256 lets anyone confirm later that the stored copy is the one received.
     */
    public function attachUpload(UploadedFile $file): static
    {
        $hash = hash_file('sha256', (string) $file->getRealPath());
        $path = $file->store(static::UPLOAD_DIRECTORY, 'local');
        $this->deleteUpload();

        // forceFill: the file columns are set only here, so models keep them guarded.
        return $this->forceFill([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'file_sha256' => $hash ?: null,
        ]);
    }

    public function hasUpload(): bool
    {
        return $this->file_path !== null && Storage::disk('local')->exists($this->file_path);
    }

    public function downloadUpload(): StreamedResponse
    {
        abort_unless($this->hasUpload(), 404);

        return Storage::disk('local')->download((string) $this->file_path, $this->file_name);
    }

    private function deleteUpload(): void
    {
        if ($this->file_path) {
            Storage::disk('local')->delete($this->file_path);
        }
    }
}
