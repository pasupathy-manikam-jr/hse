<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\VisibleBySite;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Plant or equipment that needs a periodic inspection (thorough examination), with the history of
 * those inspections and their certificates. Overdue or out-of-service equipment blocks a permit using it.
 *
 * @property int $id
 * @property int $site_id
 * @property int|null $area_id
 * @property string $asset_tag
 * @property string $name
 * @property string $type
 * @property string|null $serial_number
 * @property int|null $owner_id
 * @property int $inspection_interval_months
 * @property CarbonImmutable|null $last_inspected_on
 * @property string $status
 * @property int|null $created_by
 * @property CarbonImmutable|null $next_inspection_on
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Equipment extends Model
{
    use Auditable, HasCreator, VisibleBySite;

    public const TYPES = ['lifting', 'pressure', 'fire', 'access', 'electrical', 'vehicle', 'other'];

    public const STATUSES = ['in-service', 'out-of-service'];

    /** Reminders start this many days before an inspection is due. */
    public const DUE_SOON_DAYS = 30;

    protected $table = 'equipment';

    /** The inspection date is set by recordInspection(), never typed in. */
    protected $guarded = ['id', 'created_by', 'last_inspected_on', 'next_inspection_on'];

    protected static function booted(): void
    {
        static::saving(function (Equipment $equipment) {
            $equipment->next_inspection_on = $equipment->last_inspected_on?->addMonthsNoOverflow($equipment->inspection_interval_months);
        });

        // Through Eloquent, so each certificate file is removed with its record.
        static::deleting(function (Equipment $equipment) {
            $equipment->inspections()->get()->each->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_inspected_on' => 'date:Y-m-d',
            'next_inspection_on' => 'date:Y-m-d',
            'inspection_interval_months' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    /**
     * @return BelongsToMany<Permit, $this>
     */
    public function permits(): BelongsToMany
    {
        return $this->belongsToMany(Permit::class, 'permit_equipment');
    }

    /**
     * @return HasMany<EquipmentInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(EquipmentInspection::class)->orderByDesc('inspected_on')->orderByDesc('id');
    }

    /**
     * Record an inspection. When it is the latest, it sets the inspection date and the status:
     * a pass puts the equipment in service, a failure takes it out of service.
     *
     * @param  array{inspected_on: string, passed: bool, inspector: string, notes?: string|null}  $data
     */
    public function recordInspection(array $data, ?UploadedFile $certificate = null): EquipmentInspection
    {
        return DB::transaction(function () use ($data, $certificate) {
            $inspection = new EquipmentInspection($data);
            $inspection->equipment()->associate($this);

            if ($certificate) {
                $inspection->attachUpload($certificate);
            }

            $inspection->save();

            if ($this->last_inspected_on === null || $inspection->inspected_on->gte($this->last_inspected_on)) {
                $this->forceFill([
                    'last_inspected_on' => $inspection->inspected_on,
                    'status' => $inspection->passed ? 'in-service' : 'out-of-service',
                ])->save();
            }

            return $inspection;
        });
    }

    /**
     * In service, and its inspection stays in date through the given day.
     */
    public function isFitForUseUntil(CarbonInterface $until): bool
    {
        return $this->status === 'in-service'
            && $this->next_inspection_on !== null
            && $this->next_inspection_on->gte($until->toDateString());
    }
}
