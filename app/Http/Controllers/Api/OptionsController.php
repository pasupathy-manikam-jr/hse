<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\Contractor;
use App\Models\Observation;
use App\Models\Permit;
use App\Models\Site;
use Illuminate\Http\Request;

/**
 * Everything the app's forms choose from, in one call: load it after sign-in and keep it for
 * offline use.
 */
class OptionsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request): array
    {
        $user = $this->user($request);

        return [
            'sites' => Site::query()->availableTo($user)->with('areas:id,site_id,name')->get(['id', 'code', 'name']),
            'contractors' => Contractor::query()->orderBy('name')->get(['id', 'name']),
            'checklists' => $user->can('create-inspections')
                ? ChecklistTemplate::query()->where('active', true)->orderBy('name')->get(['id', 'name']) : [],
            'observation_types' => Observation::TYPES,
            'observation_topics' => Observation::TOPICS,
            'potentials' => Observation::POTENTIALS,
            'permit_types' => Permit::TYPES,
        ];
    }
}
