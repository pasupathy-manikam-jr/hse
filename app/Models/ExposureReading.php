<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One exposure measurement for a programme (noise dose, airborne concentration), by the noise
 * risk assessor or hygiene technician, with their report. A personal reading (on a named worker)
 * is kept 30 years, others 5 (USECHH reg. 26(4)); the app keeps them all.
 *
 * @property int $id
 * @property int $health_programme_id
 * @property CarbonImmutable $measured_on
 * @property string $value
 * @property int|null $user_id
 * @property string $assessor
 * @property string|null $notes
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $file_type
 * @property int|null $file_size
 * @property string|null $file_sha256
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ExposureReading extends Model
{
    use Auditable, HasCreator, StoresUploads;

    public const UPLOAD_DIRECTORY = 'exposure';

    protected $guarded = ['id', 'health_programme_id', 'created_by', 'file_path', 'file_name', 'file_type', 'file_size', 'file_sha256'];

    /** @var list<string> */
    protected $hidden = ['file_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_on' => 'date:Y-m-d',
            'value' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<HealthProgramme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(HealthProgramme::class, 'health_programme_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
