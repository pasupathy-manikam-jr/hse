<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\Contracts\ActionSource;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The corrective action register. With manage-actions you see every action at your sites;
 * without it, only the actions you own.
 */
class ActionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $query = $this->visible($user)
            ->with('owner:id,name', 'verifier:id,name', 'source')
            ->when($request->input('owner') === 'me', fn (Builder $q) => $q->where('owner_id', $user->id))
            ->when($request->input('owner') === 'overdue', fn (Builder $q) => $q->overdue());
        $counts = TableQuery::tabs($query, 'status', Action::STATUSES);

        $actions = TableQuery::paginate(
            $query->when(in_array($request->input('status'), Action::STATUSES, true), fn (Builder $q) => $q->where('status', $request->input('status'))),
            $request, ['number', 'description'], ['number', 'due_on', 'created_at'], 'due_on',
        );
        // The source's number, and a link if this user may open it.
        foreach ($actions->items() as $action) {
            $source = $action->source;
            $action->setAttribute('source_ref', [
                'number' => $source?->getAttribute('number'),
                'url' => $source instanceof ActionSource && $source->canBeViewedBy($user) ? $source->url() : null,
            ])->unsetRelation('source');
        }

        return Inertia::render('actions/index', [
            'actions' => $actions,
            'counts' => $counts,
            'filters' => TableQuery::filters($request, ['status', 'owner']),
        ]);
    }

    public function complete(Request $request, Action $action): RedirectResponse
    {
        $action->transitionTo('done', $this->user($request), $request->validate(['notes' => ['required', 'string', 'max:2000']])['notes']);

        return $this->done(__('Action marked done. It now needs verifying.'));
    }

    public function verify(Request $request, Action $action): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($action->isVisibleTo($user), 404);
        $action->transitionTo('verified', $user);

        return $this->done(__('Action verified.'));
    }

    public function reject(Request $request, Action $action): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($action->isVisibleTo($user), 404);
        $action->transitionTo('open', $user, $request->validate(['notes' => ['required', 'string', 'max:2000']])['notes']);

        return $this->done(__('Action sent back to its owner.'));
    }

    /**
     * @return Builder<Action>
     */
    private function visible(User $user): Builder
    {
        return $user->can('manage-actions')
            ? Action::query()->visibleTo($user)
            : Action::query()->where('owner_id', $user->id);
    }
}
