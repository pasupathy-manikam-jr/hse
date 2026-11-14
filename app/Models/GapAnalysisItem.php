<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ISO 45001 clause in a gap analysis: how well the site meets it, and the evidence.
 *
 * @property int $id
 * @property int $gap_analysis_id
 * @property int $iso_clause_id
 * @property string|null $rating
 * @property string|null $evidence
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class GapAnalysisItem extends Model
{
    use Auditable;

    public const RATINGS = ['conforms', 'partial', 'gap', 'not-applicable'];

    /** Points per rating for the score; not-applicable clauses are left out. */
    public const POINTS = ['conforms' => 1, 'partial' => 0.5, 'gap' => 0];

    protected $guarded = ['id', 'gap_analysis_id'];

    /**
     * @return BelongsTo<IsoClause, $this>
     */
    public function clause(): BelongsTo
    {
        return $this->belongsTo(IsoClause::class, 'iso_clause_id');
    }
}
