<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Site;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sites and the areas inside them, managed together on one page.
 */
class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('sites/index', [
            'sites' => TableQuery::paginate(Site::query()->with('areas:id,site_id,name')->withCount('users'), $request, ['code', 'name', 'address'], ['code', 'name', 'created_at'], 'code'),
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Site::create($this->validated($request));

        return $this->done(__('Site created.'));
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $site->update($this->validated($request, $site));

        return $this->done(__('Site updated.'));
    }

    public function destroy(Site $site): RedirectResponse
    {
        // ponytail: only users point at a site so far; each new site-bound module adds its own check here.
        if ($site->users()->exists()) {
            return $this->toast('error', __('This site still has users. Move them or deactivate the site instead.'));
        }

        $site->delete();

        return $this->done(__('Site deleted.'));
    }

    public function storeArea(Request $request, Site $site): RedirectResponse
    {
        $site->areas()->create($request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('areas')->where('site_id', $site->id)],
        ]));

        return $this->done(__('Area added.'));
    }

    public function destroyArea(Site $site, Area $area): RedirectResponse
    {
        abort_unless($area->site_id === $site->id, 404);
        $area->delete();

        return $this->done(__('Area removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('sites')->ignore($site)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ]);
    }
}
