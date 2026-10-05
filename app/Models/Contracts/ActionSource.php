<?php

namespace App\Models\Contracts;

use App\Models\User;

/**
 * A record that corrective actions can be raised from (observation, incident, ...).
 * The action register uses it to link each action back to its source.
 */
interface ActionSource
{
    public function canBeViewedBy(User $user): bool;

    public function url(): string;
}
