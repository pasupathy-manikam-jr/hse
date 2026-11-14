<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Corrective actions: the ones the person owns, and the ones waiting for them to verify.
 */
class ActionController extends Controller
{
    /**
     * `mine`: open or sent-back actions they own, by due date. `to_verify`: actions others
     * marked done that they may verify (with verify-actions). Each carries `source`: the type,
     * id and number of the record it was raised from.
     *
     * @return array{mine: list<Action>, to_verify: list<Action>}
     */
    public function index(Request $request): array
    {
        $user = $this->user($request);

        return [
            'mine' => $this->withSource(Action::query()->where('owner_id', $user->id)->where('status', 'open')->orderBy('due_on')),
            'to_verify' => $user->can('verify-actions')
                ? $this->withSource(Action::query()->listedFor($user)->where('status', 'done')->where('owner_id', '!=', $user->id)->orderBy('done_at'))
                : [],
        ];
    }

    /**
     * @param  Builder<Action>  $query
     * @return list<Action>
     */
    private function withSource(Builder $query): array
    {
        return array_values($query->with('site:id,code', 'owner:id,name', 'source')->get()
            ->each(fn (Action $a) => $a->setAttribute('source_ref', [
                'type' => Str::kebab(class_basename($a->source_type)),
                'id' => $a->source_id,
                'number' => $a->source?->getAttribute('number'),
            ])->unsetRelation('source'))
            ->all());
    }

    /** The owner marks it done, saying what was done. */
    public function complete(Request $request, Action $action): Action
    {
        $action->transitionTo('done', $this->user($request), $request->validate(['notes' => ['required', 'string', 'max:2000']])['notes']);

        return $action;
    }

    /** Someone other than the owner confirms the fix. */
    public function verify(Request $request, Action $action): Action
    {
        $user = $this->user($request);
        abort_unless($action->isVisibleTo($user), 404);
        $action->transitionTo('verified', $user);

        return $action;
    }

    /** Send it back to the owner with a reason. */
    public function reject(Request $request, Action $action): Action
    {
        $user = $this->user($request);
        abort_unless($action->isVisibleTo($user), 404);
        $action->transitionTo('open', $user, $request->validate(['notes' => ['required', 'string', 'max:2000']])['notes']);

        return $action;
    }
}
