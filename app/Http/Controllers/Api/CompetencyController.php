<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserCompetency;
use Illuminate\Http\Request;

/**
 * The signed-in person's training records, to show at the gate or to a permit issuer.
 */
class CompetencyController extends Controller
{
    /**
     * Their latest record for each competency, with `state`: valid, expiring (within 30 days)
     * or expired.
     *
     * @return list<array<string, mixed>>
     */
    public function mine(Request $request): array
    {
        return array_values($this->user($request)->competencies()->with('competency:id,name,permit_type')
            ->orderByDesc('issued_on')->orderByDesc('id')->get()
            ->unique('competency_id')
            ->map(fn (UserCompetency $c) => [
                'competency' => $c->competency,
                'issued_on' => $c->issued_on->toDateString(),
                'expires_on' => $c->expires_on?->toDateString(),
                'state' => match (true) {
                    $c->expires_on === null => 'valid',
                    $c->expires_on->isPast() && ! $c->expires_on->isToday() => 'expired',
                    $c->expires_on->lte(today()->addDays(UserCompetency::EXPIRING_DAYS)) => 'expiring',
                    default => 'valid',
                },
            ])->all());
    }
}
