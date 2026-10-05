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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An internal OH&S audit of a site against ISO 45001 (§9.2): planned → in progress → completed.
 * Findings are recorded while it is in progress; a nonconformity raises a corrective action.
 *
 * @property int $id
 * @property string $number
 * @property int $site_id
 * @property string $title
 * @property string|null $scope
 * @property int|null $lead_auditor_id
 * @property CarbonImmutable $planned_on
 * @property string $status
 * @property string|null $summary
 * @property CarbonImmutable|null $completed_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class InternalAudit extends Model implements ActionSource
{
    use Auditable, HasCreator, RaisesActions, VisibleBySite;

    public const STATUSES = ['planned', 'in-progress', 'completed'];

    protected $guarded = ['id', 'number', 'created_by', 'status', 'completed_at'];

    protected $attributes = ['status' => 'planned'];

    protected static function booted(): void
    {
        static::creating(fn (self $audit) => $audit->number = Sequence::next('AUD'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_on' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    public function canBeViewedBy(User $user): bool
    {
        return $user->can('manage-audits') && $this->isVisibleTo($user);
    }

    public function url(): string
    {
        return route('audits.show', $this);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function leadAuditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_auditor_id')->withTrashed();
    }

    /**
     * @return BelongsToMany<IsoClause, $this>
     */
    public function clauses(): BelongsToMany
    {
        return $this->belongsToMany(IsoClause::class)->orderBy('number');
    }

    /**
     * @return HasMany<AuditFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class)->orderBy('id');
    }

    public function start(): void
    {
        if ($this->status !== 'planned') {
            throw ValidationException::withMessages(['status' => __('Only a planned audit can be started.')]);
        }

        $this->forceFill(['status' => 'in-progress'])->save();
    }

    /**
     * Record a finding. A nonconformity raises a corrective action (owner and due date required)
     * in the same transaction.
     *
     * @param  array{type: string, iso_clause_id: int|null, description: string}  $finding
     * @param  array{owner_id: int, due_on: string}|null  $action
     */
    public function addFinding(array $finding, ?array $action = null): AuditFinding
    {
        if ($this->status !== 'in-progress') {
            throw ValidationException::withMessages(['status' => __('Findings are recorded while the audit is in progress.')]);
        }

        return DB::transaction(function () use ($finding, $action) {
            $record = $this->findings()->create($finding);

            if ($record->isNonconformity() && $action) {
                $clause = $record->clause ? " (§{$record->clause->number})" : '';
                $raised = $this->raiseAction([
                    'description' => __('Correct audit nonconformity:clause: :d', ['clause' => $clause, 'd' => $record->description]),
                    'control_level' => 'administrative',
                    'priority' => $record->type === 'major-nonconformity' ? 'high' : 'medium',
                    ...$action,
                ]);
                $record->update(['action_id' => $raised->id]);
            }

            return $record;
        });
    }

    public function complete(): void
    {
        if ($this->status !== 'in-progress') {
            throw ValidationException::withMessages(['status' => __('Only an audit in progress can be completed.')]);
        }

        if (blank($this->summary)) {
            throw ValidationException::withMessages(['status' => __('Write the audit summary first.')]);
        }

        $this->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
    }
}
