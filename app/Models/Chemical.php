<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\StoresUploads;
use App\Models\Concerns\VisibleBySite;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A hazardous substance held at a site, with its safety data sheet (the stored file) and the
 * COSHH assessment covering its use.
 *
 * @property int $id
 * @property int $site_id
 * @property int|null $area_id
 * @property string $name
 * @property string|null $supplier
 * @property string|null $product_code
 * @property list<string>|null $hazards
 * @property string|null $max_quantity
 * @property string|null $unit
 * @property CarbonImmutable|null $sds_issued_on
 * @property int|null $risk_assessment_id
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $file_type
 * @property int|null $file_size
 * @property string|null $file_sha256
 * @property int|null $created_by
 * @property-read bool $needs_coshh
 * @property-read bool $sds_outdated
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Chemical extends Model
{
    use Auditable, HasCreator, StoresUploads, VisibleBySite;

    public const UPLOAD_DIRECTORY = 'sds';

    /** GHS hazard pictograms. */
    public const HAZARDS = ['explosive', 'flammable', 'oxidising', 'gas-under-pressure', 'corrosive', 'acute-toxicity', 'health-hazard', 'harmful', 'environment'];

    /** Hazards to people's health: these need a COSHH assessment. */
    public const HEALTH_HAZARDS = ['corrosive', 'acute-toxicity', 'health-hazard', 'harmful'];

    /** An SDS older than this should be checked with the supplier for a newer version. */
    public const SDS_MAX_AGE_YEARS = 5;

    protected $guarded = ['id', 'created_by', 'file_path', 'file_name', 'file_type', 'file_size', 'file_sha256'];

    /** @var list<string> */
    protected $appends = ['needs_coshh', 'sds_outdated'];

    /** @var list<string> */
    protected $hidden = ['file_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hazards' => 'array',
            'sds_issued_on' => 'date:Y-m-d',
            'max_quantity' => 'decimal:2',
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
     * @return BelongsTo<RiskAssessment, $this>
     */
    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(RiskAssessment::class);
    }

    /**
     * A health hazard without an approved COSHH assessment linked.
     *
     * @return Attribute<bool, never>
     */
    protected function needsCoshh(): Attribute
    {
        return Attribute::get(fn () => array_intersect($this->hazards ?? [], self::HEALTH_HAZARDS) !== []
            && $this->riskAssessment?->status !== 'approved');
    }

    /**
     * No SDS on file, or one issued more than five years ago.
     *
     * @return Attribute<bool, never>
     */
    protected function sdsOutdated(): Attribute
    {
        return Attribute::get(fn () => $this->file_path === null || $this->sds_issued_on === null
            || $this->sds_issued_on->lt(today()->subYears(self::SDS_MAX_AGE_YEARS)));
    }
}
