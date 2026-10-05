<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasSignatures;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One revision of a controlled document: draft → in review → effective → superseded.
 * Approving (signed) makes it effective and supersedes the previous effective revision.
 *
 * @property int $id
 * @property int $document_id
 * @property string $revision
 * @property string|null $change_summary
 * @property string $status
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $file_type
 * @property int|null $file_size
 * @property string|null $file_sha256
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DocumentRevision extends Model
{
    use Auditable, HasCreator, HasSignatures, StoresUploads;

    public const UPLOAD_DIRECTORY = 'documents';

    public const STATUSES = ['draft', 'in-review', 'effective', 'superseded'];

    protected $guarded = ['id', 'created_by', 'status', 'approved_by', 'approved_at', 'file_path', 'file_name', 'file_type', 'file_size', 'file_sha256'];

    protected $attributes = ['status' => 'draft'];

    /**
     * Controlled documents are often Word or Excel files, not only PDFs.
     *
     * @return list<string>
     */
    public static function uploadExtensions(): array
    {
        return [...self::UPLOAD_EXTENSIONS, 'doc', 'docx', 'xls', 'xlsx'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    /**
     * People asked to read this revision; acknowledged_at is set once they confirm.
     *
     * @return BelongsToMany<User, $this>
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_acknowledgements')->withPivot('acknowledged_at')->withTimestamps();
    }

    public function submit(): void
    {
        $this->move('draft', 'in-review', match (true) {
            $this->file_path === null => 'Upload the document file first.',
            blank($this->change_summary) => 'Describe what changed in this revision.',
            default => null,
        });
    }

    public function returnToDraft(): void
    {
        $this->move('in-review', 'draft');
    }

    /**
     * Make this revision effective, signed with the approver's password. The author may not approve it.
     */
    public function approve(User $by, ?string $password): void
    {
        $this->move('in-review', 'effective', $this->created_by === $by->id ? 'You wrote this revision, so someone else must approve it.' : null, $by, $password);
    }

    /**
     * @param  string|null  $error  untranslated reason the move is refused
     */
    private function move(string $from, string $to, ?string $error = null, ?User $by = null, ?string $password = null): void
    {
        if ($this->status !== $from) {
            throw ValidationException::withMessages(['status' => __('A :from revision cannot be marked :to.', ['from' => $this->status, 'to' => $to])]);
        }

        if ($error !== null) {
            throw ValidationException::withMessages(['status' => __($error)]);
        }

        DB::transaction(function () use ($to, $by, $password) {
            if ($to === 'effective' && $by) {
                $this->sign($by, $password, 'approved');

                $this->document->revisions()->where('status', 'effective')->get()
                    ->each(fn (self $old) => $old->forceFill(['status' => 'superseded'])->save());

                $this->document->forceFill(['next_review_on' => CarbonImmutable::today()->addMonths($this->document->review_interval_months)])->save();
            }

            $this->forceFill([
                'status' => $to,
                ...($to === 'effective' ? ['approved_by' => $by?->id, 'approved_at' => now()] : []),
            ])->save();
        });
    }
}
