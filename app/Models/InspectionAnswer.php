<?php

namespace App\Models;

use App\Models\Concerns\HasPhotos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answered question of an inspection, with its own copy of the question.
 *
 * @property int $id
 * @property int $inspection_id
 * @property string $question
 * @property string $response_type
 * @property string|null $min
 * @property string|null $max
 * @property bool $critical
 * @property int $sort
 * @property string|null $answer
 * @property bool|null $passed
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class InspectionAnswer extends Model
{
    use HasPhotos;

    protected $guarded = ['id', 'inspection_id', 'passed'];

    protected static function booted(): void
    {
        // A block, not an arrow fn: returning false from "saving" would cancel the save of every failed answer.
        static::saving(function (self $answer): void {
            $answer->passed = $answer->evaluate();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'critical' => 'boolean',
            'min' => 'decimal:2',
            'max' => 'decimal:2',
            'passed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Inspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function canBeViewedBy(User $user): bool
    {
        return $this->inspection->canBeViewedBy($user);
    }

    /**
     * Pass (true), fail (false), or neither (null: unanswered, N/A, or free text).
     */
    public function evaluate(): ?bool
    {
        if (blank($this->answer)) {
            return null;
        }

        return match ($this->response_type) {
            'yes-no-na' => match ($this->answer) {
                'yes' => true,
                'no' => false,
                default => null,
            },
            'rating' => (int) $this->answer >= (int) ($this->min ?? ChecklistItem::RATING_PASS),
            'number' => is_numeric($this->answer)
                && ($this->min === null || (float) $this->answer >= (float) $this->min)
                && ($this->max === null || (float) $this->answer <= (float) $this->max),
            default => null,
        };
    }
}
