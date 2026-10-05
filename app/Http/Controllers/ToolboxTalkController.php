<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Site;
use App\Models\ToolboxTalk;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ToolboxTalkController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $sites = Site::query()->availableTo($user)->get(['id', 'code', 'name']);
        $query = ToolboxTalk::query()->visibleTo($user)
            ->with('site:id,code', 'presenter:id,name', 'incident:id,number', 'attendees:id,name')
            ->withCount('attendees')
            ->when($request->filled('site_id'), fn (Builder $q) => $q->where('site_id', $request->integer('site_id')));

        return Inertia::render('toolbox-talks/index', [
            'talks' => TableQuery::paginate($query, $request, ['number', 'topic'], ['number', 'held_on'], 'held_on'),
            'sites' => $sites,
            'people' => User::query()->where(fn (Builder $q) => $q->whereNull('site_id')->orWhereIn('site_id', $sites->pluck('id')))
                ->orderBy('name')->get(['id', 'name', 'site_id']),
            'incidents' => Incident::query()->visibleTo($user)->latest('occurred_at')->limit(50)->get(['id', 'number', 'title', 'site_id']),
            'filters' => TableQuery::filters($request, ['site_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'site_id' => ['required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'topic' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'held_on' => ['required', 'date', 'before_or_equal:today'],
            'presenter_id' => ['required', Rule::exists('users', 'id')],
            'incident_id' => ['nullable', Rule::exists('incidents', 'id')->where('site_id', $request->integer('site_id'))],
            'attendee_ids' => ['required', 'array', 'min:1'],
            'attendee_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $talk = ToolboxTalk::create(Arr::except($data, 'attendee_ids'));
        $talk->attendees()->sync($data['attendee_ids']);

        return $this->done(__(':number recorded with :n attendees.', ['number' => $talk->number, 'n' => count($data['attendee_ids'])]));
    }

    public function destroy(Request $request, ToolboxTalk $toolboxTalk): RedirectResponse
    {
        abort_unless($toolboxTalk->isVisibleTo($this->user($request)), 404);
        $toolboxTalk->delete();

        return $this->done(__('Toolbox talk deleted.'));
    }
}
