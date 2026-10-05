<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audit finding. Never edited or deleted; a nonconformity carries the action it raised.
 *
 * @property int $id
 * @property int $internal_audit_id
 * @property string $type
 * @property int|null $iso_clause_id
 * @property string $description
 * @property int|null $action_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class AuditFinding extends Model
{
    use Auditable, HasCreator;

    /** Nonconformities raise a corrective action; observations and opportunities are noted only. */
    public const TYPES = ['major-nonconformity', 'minor-nonconformity', 'observation', 'opportunity'];

    protected $guarded = ['id', 'created_by'];

    /**
     * @return BelongsTo<InternalAudit, $this>
     */
    public function internalAudit(): BelongsTo
    {
        return $this->belongsTo(InternalAudit::class);
    }

    /**
     * @return BelongsTo<IsoClause, $this>
     */
    public function clause(): BelongsTo
    {
        return $this->belongsTo(IsoClause::class, 'iso_clause_id');
    }

    /**
     * @return BelongsTo<Action, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }

    public function isNonconformity(): bool
    {
        return str_ends_with($this->type, 'nonconformity');
    }
}
