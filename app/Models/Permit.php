<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasSignatures;
use App\Models\Concerns\VisibleBySite;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A permit to work for high-risk work at one site, for at most one shift.
 * Requested → approved → active ⇄ suspended → closed, or cancelled before work starts.
 * Approval and closing are signed by re-entering the password.
 *
 * @property int $id
 * @property string $number
 * @property string $type
 * @property int $site_id
 * @property int|null $area_id
 * @property int|null $contractor_id
 * @property int $risk_assessment_id
 * @property string $description
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable $valid_to
 * @property array<string, bool>|null $precautions
 * @property string $status
 * @property string|null $status_reason
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $closed_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Permit extends Model
{
    use Auditable, HasCreator, HasSignatures, VisibleBySite;

    public const TYPES = ['hot-work', 'confined-space', 'work-at-height', 'electrical', 'excavation', 'lifting', 'general'];

    public const STATUSES = ['requested', 'approved', 'active', 'suspended', 'closed', 'cancelled'];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'requested' => ['approved', 'cancelled'],
        'approved' => ['active', 'cancelled'],
        'active' => ['suspended', 'closed'],
        'suspended' => ['active', 'closed'],
    ];

    /** One shift at most; a longer job is a new permit. */
    public const MAX_HOURS = 12;

    /** A confined-space permit can only start with a passing gas test this recent. */
    public const GAS_TEST_HOURS = 2;

    /** Confined-space entry: oxygen must be within this range (%), inclusive. */
    public const OXYGEN_RANGE = [19.5, 23.5];

    /**
     * Confined-space entry: these must stay below the limit (LEL in % of the lower explosive
     * limit, H₂S and CO in ppm).
     *
     * @var array<string, float>
     */
    public const GAS_BELOW = ['lel' => 10.0, 'h2s' => 10.0, 'co' => 25.0];

    /**
     * Precautions to confirm before approval, per type (every type also gets the general ones).
     *
     * @var array<string, array<string, string>>
     */
    public const PRECAUTIONS = [
        'general' => [
            'briefed' => 'Workers briefed on the risk assessment and this permit',
            'area_barricaded' => 'Work area barricaded and signed',
            'emergency' => 'Emergency arrangements known (alarm, first aid, assembly point)',
        ],
        'hot-work' => [
            'combustibles_removed' => 'Combustibles removed or covered within 10 m',
            'extinguisher' => 'Fire extinguisher at the work point',
            'fire_watch' => 'Fire watch during work and 60 minutes after',
        ],
        'confined-space' => [
            'isolated' => 'All inlets and energy sources isolated',
            'ventilated' => 'Space ventilated',
            'standby' => 'Standby person at the entrance',
            'rescue' => 'Rescue equipment and plan in place',
        ],
        'work-at-height' => [
            'edge_protection' => 'Edge protection or fall arrest in place',
            'equipment_inspected' => 'Access equipment inspected and tagged',
            'exclusion_below' => 'Exclusion zone below the work',
        ],
        'electrical' => [
            'isolated_locked' => 'Circuit isolated, locked and tagged',
            'proved_dead' => 'Proved dead with a tested voltage indicator',
            'insulated_tools' => 'Insulated tools and PPE provided',
        ],
        'excavation' => [
            'services_located' => 'Underground services located and marked',
            'support' => 'Sides supported, battered or stepped',
            'edge_barrier' => 'Edge barrier and safe access in place',
        ],
        'lifting' => [
            'lift_plan' => 'Lift plan approved',
            'gear_certified' => 'Lifting gear in date and inspected',
            'swing_radius' => 'Swing radius barricaded; banksman assigned',
        ],
    ];

    /**
     * Types that must not run in the same area at the same time.
     *
     * @var array<string, list<string>>
     */
    public const CONFLICTS = [
        'hot-work' => ['confined-space'],
        'confined-space' => ['hot-work'],
    ];

    protected $guarded = ['id', 'number', 'status', 'status_reason', 'approved_by', 'approved_at', 'closed_at', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (self $permit) {
            $permit->number = Sequence::next('PTW');
            $permit->status = 'requested';
        });
    }

    /**
     * The precautions for a type: the general ones, then the type's own.
     *
     * @return array<string, string>
     */
    public static function precautionsFor(string $type): array
    {
        return [...self::PRECAUTIONS['general'], ...self::PRECAUTIONS[$type] ?? []];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'precautions' => 'array',
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
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<RiskAssessment, $this>
     */
    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(RiskAssessment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permit_workers')->withTrashed()->orderBy('name');
    }

    /**
     * @return HasMany<PermitGasTest, $this>
     */
    public function gasTests(): HasMany
    {
        return $this->hasMany(PermitGasTest::class)->orderByDesc('tested_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<PermitIsolation, $this>
     */
    public function isolations(): HasMany
    {
        return $this->hasMany(PermitIsolation::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['closed', 'cancelled'], true);
    }

    /**
     * Competencies the permit type requires that a named worker does not hold for the whole
     * permit period, as "Name: Competency" lines.
     *
     * @return list<string>
     */
    public function missingCompetencies(): array
    {
        $required = Competency::query()->where('permit_type', $this->type)->get();
        $missing = [];

        foreach ($this->workers()->with('competencies')->get() as $worker) {
            foreach ($required as $competency) {
                if (! $worker->hasValidCompetency($competency, $this->valid_to)) {
                    $missing[] = "{$worker->name}: {$competency->name}";
                }
            }
        }

        return $missing;
    }

    /**
     * Why this permit cannot be approved yet (empty when it can).
     *
     * @return list<string>
     */
    public function approvalProblems(): array
    {
        $problems = [];
        $unchecked = array_diff_key(self::precautionsFor($this->type), array_filter($this->precautions ?? []));

        if ($this->riskAssessment->status !== 'approved') {
            $problems[] = __('The risk assessment is no longer the approved revision.');
        }

        if ($this->contractor && ! $this->contractor->canWork()) {
            $problems[] = __(':name is not approved, or its insurance does not cover the permit period.', ['name' => $this->contractor->name]);
        }

        if (! $this->workers()->exists()) {
            $problems[] = __('Name at least one worker.');
        }

        if ($unchecked !== []) {
            $problems[] = __('Confirm every precaution (:n unchecked).', ['n' => count($unchecked)]);
        }

        foreach ($this->missingCompetencies() as $line) {
            $problems[] = __('No valid competency: :line', ['line' => $line]);
        }

        if ($this->type === 'confined-space' && ! $this->gasTests()->where('passed', true)->exists()) {
            $problems[] = __('Record a passing gas test.');
        }

        return $problems;
    }

    /**
     * Open permits in the same area whose type conflicts with this one and whose time overlaps.
     *
     * @return Collection<int, Permit>
     */
    public function conflicts(): Collection
    {
        $types = self::CONFLICTS[$this->type] ?? [];

        if ($types === [] || $this->area_id === null) {
            return new Collection;
        }

        return self::query()->whereKeyNot($this->id)
            ->where('area_id', $this->area_id)
            ->whereIn('type', $types)
            ->whereIn('status', ['requested', 'approved', 'active', 'suspended'])
            ->where('valid_from', '<', $this->valid_to)
            ->where('valid_to', '>', $this->valid_from)
            ->get(['id', 'number', 'type', 'status', 'valid_from', 'valid_to']);
    }

    /**
     * Move the permit on. Approving and closing are signed (password re-entered); suspending
     * and cancelling need a reason.
     */
    public function transitionTo(string $status, User $by, ?string $reason = null, ?string $password = null): void
    {
        if (! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('A :from permit cannot be :to.', ['from' => $this->status, 'to' => $status])]);
        }

        $latestGas = $this->gasTests()->first();
        $error = match ($status) {
            'approved' => match (true) {
                $by->id === $this->created_by => __('The person who requested the permit cannot approve it.'),
                ($problems = $this->approvalProblems()) !== [] => implode(' ', $problems),
                default => null,
            },
            'active' => match (true) {
                now()->lt($this->valid_from) || now()->gt($this->valid_to) => __('The permit is only valid from :from to :to.', ['from' => $this->valid_from->format('Y-m-d H:i'), 'to' => $this->valid_to->format('Y-m-d H:i')]),
                $this->type === 'confined-space' && (! $latestGas?->passed || $latestGas->tested_at->lt(now()->subHours(self::GAS_TEST_HOURS))) => __('Entry needs a passing gas test from the last :h hours.', ['h' => self::GAS_TEST_HOURS]),
                default => null,
            },
            'suspended', 'cancelled' => blank($reason) ? __('Give a reason.') : null,
            'closed' => $this->isolations()->whereNull('removed_at')->exists() ? __('Remove every isolation before closing.') : null,
        };

        if ($error) {
            throw ValidationException::withMessages(['status' => $error]);
        }

        DB::transaction(function () use ($status, $by, $reason, $password) {
            if (in_array($status, ['approved', 'closed'], true)) {
                $this->sign($by, $password, $status);
            }

            $this->forceFill(match ($status) {
                'approved' => ['approved_by' => $by->id, 'approved_at' => now()],
                'closed' => ['closed_at' => now()],
                default => [],
            })->forceFill(['status' => $status, 'status_reason' => $reason ?? ($status === 'active' ? null : $this->status_reason)])->save();
        });
    }
}
