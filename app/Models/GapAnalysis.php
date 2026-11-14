<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\RaisesActions;
use App\Models\Concerns\VisibleBySite;
use App\Models\Contracts\ActionSource;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A site's self-assessment against every ISO 45001 clause (in progress → completed), with
 * actions raised to close the gaps. Each new analysis starts from the full clause list.
 *
 * @property int $id
 * @property string $number
 * @property int $site_id
 * @property string $title
 * @property CarbonImmutable $assessed_on
 * @property string $status
 * @property string|null $summary
 * @property CarbonImmutable|null $completed_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class GapAnalysis extends Model implements ActionSource
{
    use Auditable, HasCreator, RaisesActions, VisibleBySite;

    public const STATUSES = ['in-progress', 'completed'];

    protected $guarded = ['id', 'number', 'created_by', 'status', 'completed_at'];

    protected $attributes = ['status' => 'in-progress'];

    protected static function booted(): void
    {
        static::creating(function (self $analysis) {
            $analysis->number = Sequence::next('GAP');
        });

        static::created(function (self $analysis) {
            $analysis->items()->createMany(IsoClause::query()->orderBy('id')->pluck('id')->map(fn (int $id) => ['iso_clause_id' => $id])->all());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessed_on' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    public function canBeViewedBy(User $user): bool
    {
        return $user->can('manage-audits') && $this->isVisibleTo($user);
    }

    public function url(): string
    {
        return route('gap-analyses.show', $this);
    }

    /**
     * @return HasMany<GapAnalysisItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GapAnalysisItem::class)->orderBy('iso_clause_id');
    }

    /**
     * Score in % per ISO 45001 section (4 to 10) and overall: conforms 1, partial ½, gap 0,
     * not-applicable and unrated clauses left out. Null where nothing is scored yet.
     *
     * @return array{sections: array<string, float|null>, overall: float|null}
     */
    public function scores(): array
    {
        $items = $this->items()->with('clause:id,number')->get();
        $score = function (Collection $group): ?float {
            $points = $group->map(fn (GapAnalysisItem $i) => GapAnalysisItem::POINTS[$i->rating] ?? null)->filter(fn ($p) => $p !== null);

            return $points->isEmpty() ? null : round(100 * $points->sum() / $points->count(), 1);
        };

        return [
            'sections' => $items->groupBy(fn (GapAnalysisItem $i) => Str::before($i->clause->number, '.'))->map($score)->all(),
            'overall' => $score($items),
        ];
    }

    /**
     * @param  array<int, array{rating: string|null, evidence: string|null}>  $ratings  keyed by item id
     */
    public function rate(array $ratings): void
    {
        $this->ensureInProgress();

        foreach ($this->items()->whereKey(array_keys($ratings))->get() as $item) {
            $item->update($ratings[$item->id]);
        }
    }

    /**
     * Every clause rated, a gap or partial rating explained, and a summary written.
     */
    public function complete(string $summary): void
    {
        $this->ensureInProgress();
        $items = $this->items()->with('clause:id,number')->get();

        if ($items->contains(fn (GapAnalysisItem $i) => $i->rating === null)) {
            throw ValidationException::withMessages(['status' => __('Rate every clause first.')]);
        }

        $unexplained = $items->filter(fn (GapAnalysisItem $i) => in_array($i->rating, ['gap', 'partial'], true) && blank($i->evidence));

        if ($unexplained->isNotEmpty()) {
            throw ValidationException::withMessages(['status' => __('Describe what is missing for §:clauses.', ['clauses' => $unexplained->map(fn (GapAnalysisItem $i) => $i->clause->number)->implode(', §')])]);
        }

        $this->forceFill(['summary' => $summary, 'status' => 'completed', 'completed_at' => now()])->save();
    }

    private function ensureInProgress(): void
    {
        if ($this->status !== 'in-progress') {
            throw ValidationException::withMessages(['status' => __('This gap analysis is completed.')]);
        }
    }
}
