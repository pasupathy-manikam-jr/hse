<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's record of a competency, with its certificate. A newer record for the same
 * competency replaces the older one in the matrix.
 *
 * @property int $id
 * @property int $user_id
 * @property int $competency_id
 * @property CarbonImmutable $issued_on
 * @property CarbonImmutable|null $expires_on
 * @property string|null $reference
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $file_type
 * @property int|null $file_size
 * @property string|null $file_sha256
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class UserCompetency extends Model
{
    use Auditable, HasCreator, StoresUploads;

    public const UPLOAD_DIRECTORY = 'competencies';

    /** Shown as "expiring" (and in reminders) this many days before expiry. */
    public const EXPIRING_DAYS = 30;

    protected $guarded = ['id', 'created_by', 'file_path', 'file_name', 'file_type', 'file_size', 'file_sha256'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date:Y-m-d',
            'expires_on' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Competency, $this>
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /**
     * Valid through the whole of its expiry day.
     */
    public function isValidOn(CarbonInterface $day): bool
    {
        return $this->expires_on === null || $this->expires_on->endOfDay()->gte($day);
    }
}
