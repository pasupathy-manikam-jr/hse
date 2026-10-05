<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One hazard on a risk assessment, scored likelihood × severity (1–5 each) before and
 * after the additional controls.
 *
 * @property int $id
 * @property int $risk_assessment_id
 * @property string $hazard
 * @property string|null $who_at_risk
 * @property string|null $existing_controls
 * @property int $likelihood
 * @property int $severity
 * @property string|null $additional_controls
 * @property int $residual_likelihood
 * @property int $residual_severity
 * @property-read int $initial_score
 * @property-read int $residual_score
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class RiskHazard extends Model
{
    use Auditable;

    protected $guarded = ['id', 'risk_assessment_id'];

    /** @var list<string> */
    protected $appends = ['initial_score', 'residual_score'];

    /**
     * @return BelongsTo<RiskAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(RiskAssessment::class, 'risk_assessment_id');
    }

    /**
     * @return Attribute<int, never>
     */
    protected function initialScore(): Attribute
    {
        return Attribute::get(fn () => $this->likelihood * $this->severity);
    }

    /**
     * @return Attribute<int, never>
     */
    protected function residualScore(): Attribute
    {
        return Attribute::get(fn () => $this->residual_likelihood * $this->residual_severity);
    }
}
