<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One inspection or thorough examination of a piece of equipment, with its certificate.
 * Never edited: a correction is a new record.
 *
 * @property int $id
 * @property int $equipment_id
 * @property CarbonImmutable $inspected_on
 * @property bool $passed
 * @property string $inspector
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
class EquipmentInspection extends Model
{
    use Auditable, HasCreator, StoresUploads;

    public const UPLOAD_DIRECTORY = 'equipment';

    protected $guarded = ['id', 'equipment_id', 'created_by', 'file_path', 'file_name', 'file_type', 'file_size', 'file_sha256'];

    /** @var list<string> */
    protected $hidden = ['file_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inspected_on' => 'date:Y-m-d',
            'passed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
