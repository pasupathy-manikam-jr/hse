<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One monthly figure for a site: hours worked (the exposure injury rates divide by) or an
 * environmental quantity (waste, water, energy, fuel).
 *
 * @property int $id
 * @property int $site_id
 * @property CarbonImmutable $month
 * @property string $metric
 * @property string $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SiteMetric extends Model
{
    public const HOURS = 'hours-worked';

    /** Average number of workers in the month: the base of the JKKP 8 incident and fatality rates. */
    public const WORKERS = 'workers';

    /**
     * Metric => [label, unit]. Environmental metrics follow hours worked.
     *
     * @var array<string, array{string, string}>
     */
    public const METRICS = [
        self::HOURS => ['Hours worked', 'h'],
        self::WORKERS => ['Average workers', 'people'],
        'general-waste' => ['General waste', 't'],
        'hazardous-waste' => ['Hazardous waste', 't'],
        'recycled-waste' => ['Recycled waste', 't'],
        'water' => ['Water', 'm³'],
        'electricity' => ['Electricity', 'kWh'],
        'diesel' => ['Diesel', 'L'],
    ];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date:Y-m',
            'value' => 'decimal:2',
        ];
    }

    /**
     * Environmental metrics only (everything except hours worked and workers).
     *
     * @return array<string, array{string, string}>
     */
    public static function environmental(): array
    {
        return array_diff_key(self::METRICS, [self::HOURS => true, self::WORKERS => true]);
    }
}
