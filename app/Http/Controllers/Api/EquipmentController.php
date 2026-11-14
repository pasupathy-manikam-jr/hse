<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;

/**
 * Check a piece of equipment before use, by its asset tag (typed or scanned).
 */
class EquipmentController extends Controller
{
    /**
     * The equipment with its latest inspections; `fit_for_use` is true when it is in service and
     * its inspection is in date today.
     *
     * @return array<string, mixed>
     */
    public function show(Request $request, string $assetTag): array
    {
        $equipment = Equipment::query()->visibleTo($this->user($request))->where('asset_tag', $assetTag)
            ->with('site:id,code', 'area:id,name', 'owner:id,name')->firstOrFail();

        return [
            'equipment' => $equipment,
            'fit_for_use' => $equipment->isFitForUseUntil(today()),
            'inspections' => $equipment->inspections()->limit(5)->get(['id', 'equipment_id', 'inspected_on', 'passed', 'inspector', 'notes']),
        ];
    }
}
