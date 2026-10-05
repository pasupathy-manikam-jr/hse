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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A checklist inspection at a site. It copies the template's questions when started, so later
 * template edits never change it. In progress → completed; completing scores it and raises an
 * action for every failed answer.
 *
 * @property int $id
 * @property string $number
 * @property int|null $checklist_template_id
 * @property string $template_name
 * @property int $site_id
 * @property int|null $area_id
 * @property string $status
 * @property int|null $score
 * @property string|null $notes
 * @property CarbonImmutable|null $completed_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Inspection extends Model implements ActionSource
{
    use Auditable, HasCreator, RaisesActions, VisibleBySite;

    public const STATUSES = ['in-progress', 'completed'];

    protected $guarded = ['id', 'number', 'status', 'score', 'completed_at', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (self $inspection) {
            $inspection->number = Sequence::next('INS');
            $inspection->status = 'in-progress';
        });
    }

    /**
     * Start an inspection from a template, copying its questions.
     */
    public static function start(ChecklistTemplate $template, int $siteId, ?int $areaId): self
    {
        return DB::transaction(function () use ($template, $siteId, $areaId) {
            $inspection = self::create([
                'checklist_template_id' => $template->id, 'template_name' => $template->name, 'site_id' => $siteId, 'area_id' => $areaId,
            ]);

            foreach ($template->items as $item) {
                $inspection->answers()->create($item->only(['question', 'response_type', 'min', 'max', 'critical', 'sort']));
            }

            return $inspection;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function canBeViewedBy(User $user): bool
    {
        return $user->can('manage-inspections') && $this->isVisibleTo($user);
    }

    public function url(): string
    {
        return route('inspections.show', $this);
    }

    /**
     * @return HasMany<InspectionAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(InspectionAnswer::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Complete the inspection: every scored question answered, then the score is the share of
     * passes among answers that pass or fail, and each failure raises an action for the inspector.
     */
    public function complete(User $by): void
    {
        if ($this->status !== 'in-progress') {
            throw ValidationException::withMessages(['status' => __('This inspection is already completed.')]);
        }

        $answers = $this->answers()->get();
        $unanswered = $answers->filter(fn (InspectionAnswer $a) => $a->response_type !== 'text' && blank($a->answer));

        if ($unanswered->isNotEmpty()) {
            throw ValidationException::withMessages(['status' => trans_choice('{1} One question is not answered yet.|[2,*] :count questions are not answered yet.', $unanswered->count())]);
        }

        $scored = $answers->whereNotNull('passed');

        DB::transaction(function () use ($by, $answers, $scored) {
            $this->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'score' => $scored->isEmpty() ? null : (int) round(100 * $scored->whereStrict('passed', true)->count() / $scored->count()),
            ])->save();

            // Strict: a loose where() would treat N/A and text answers (null) as failures.
            foreach ($answers->whereStrict('passed', false) as $failed) {
                $this->raiseAction([
                    'description' => Str::limit(__('Fix: :question', ['question' => $failed->question]).($failed->notes ? " ({$failed->notes})" : ''), 2000),
                    'control_level' => 'administrative',
                    'priority' => $failed->critical ? 'high' : 'medium',
                    'owner_id' => $by->id,
                    'due_on' => today()->addDays($failed->critical ? 1 : 7)->toDateString(),
                ]);
            }
        });
    }
}
