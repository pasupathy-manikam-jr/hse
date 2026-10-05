<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Writes created, updated and deleted events to the audit trail, plus any
 * custom event recorded with audit(). Hidden attributes are never logged.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (self $model) => $model->audit('created', null, $model->auditValues($model->getAttributes())));

        static::updated(function (self $model) {
            $new = $model->auditValues($model->getChanges());

            if ($new !== []) {
                $model->audit('updated', array_intersect_key($model->auditValues($model->getOriginal()), $new), $new);
            }
        });

        static::deleted(fn (self $model) => $model->audit('deleted', $model->auditValues($model->getOriginal()), null));
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function audit(string $event, ?array $old, ?array $new): void
    {
        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function auditValues(array $attributes): array
    {
        return array_diff_key($attributes, array_flip([...$this->getHidden(), 'created_at', 'updated_at']));
    }
}
