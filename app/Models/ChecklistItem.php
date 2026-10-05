<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checklist question. yes-no-na fails on "no"; rating (1–5) fails below min (3 by default);
 * number fails outside min–max;
 * text is recorded only. A critical item's failure raises a high-priority action due next day.
 *
 * @property int $id
 * @property int $checklist_template_id
 * @property string $question
 * @property string $response_type
 * @property string|null $min
 * @property string|null $max
 * @property bool $critical
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ChecklistItem extends Model
{
    use Auditable;

    public const RESPONSE_TYPES = ['yes-no-na', 'rating', 'number', 'text'];

    /** A rating question passes at this score or above unless the item sets its own minimum. */
    public const RATING_PASS = 3;

    protected $guarded = ['id', 'checklist_template_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'critical' => 'boolean',
            'min' => 'decimal:2',
            'max' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ChecklistTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }
}
