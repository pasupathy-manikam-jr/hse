<?php

namespace App\Http\Controllers;

use App\Models\ShiftHandover;
use App\Models\Site;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShiftHandoverController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $sites = Site::query()->availableTo($user)->get(['id', 'code', 'name']);

        return Inertia::render('handovers/index', [
            'handovers' => TableQuery::paginate(
                ShiftHandover::query()->visibleTo($user)->with('site:id,code', 'creator:id,name', 'recipient:id,name'),
                $request, ['number', 'notes'], ['number', 'created_at'], 'created_at',
            ),
            'sites' => $sites,
            'people' => User::query()->whereKeyNot($user->id)
                ->where(fn (Builder $q) => $q->whereNull('site_id')->orWhereIn('site_id', $sites->pluck('id')))
                ->permission('create-permits')->orderBy('name')->get(['id', 'name', 'site_id']),
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $handover = ShiftHandover::create($request->validate([
            'site_id' => ['required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'shift' => ['required', Rule::in(ShiftHandover::SHIFTS)],
            'to_user_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::notIn([$user->id])],
            'notes' => ['required', 'string', 'max:5000'],
        ]));

        return $this->done(__(':number handed over with :n open permits.', ['number' => $handover->number, 'n' => count($handover->open_permits)]));
    }

    public function acknowledge(Request $request, ShiftHandover $handover): RedirectResponse
    {
        abort_unless($handover->isVisibleTo($this->user($request)), 404);
        $handover->acknowledge($this->user($request));

        return $this->done(__('Handover acknowledged.'));
    }
}
