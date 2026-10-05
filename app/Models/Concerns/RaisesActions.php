<?php

namespace App\Models\Concerns;

use App\Models\Action;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

/**
 * Raise corrective actions from a site-bound record with a `status` (closed records take none).
 */
trait RaisesActions
{
    /**
     * @return MorphMany<Action, $this>
     */
    public function actions(): MorphMany
    {
        return $this->morphMany(Action::class, 'source')->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function raiseAction(array $data): Action
    {
        if ($this->status === 'closed') {
            throw ValidationException::withMessages(['description' => __('This record is closed.')]);
        }

        $action = $this->actions()->create([...$data, 'site_id' => $this->site_id]);
        $this->actionRaised();

        return $action;
    }

    public function hasUnverifiedActions(): bool
    {
        return $this->actions()->where('status', '!=', 'verified')->exists();
    }

    /**
     * Hook for the source's own workflow (an observation becomes "actioned").
     */
    protected function actionRaised(): void {}
}
