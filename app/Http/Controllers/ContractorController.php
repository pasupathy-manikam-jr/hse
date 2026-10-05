<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractorController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Contractor::query()->withCount('workers')
            ->when($request->input('approved') === '1', fn (Builder $q) => $q->where('approved', true))
            ->when($request->input('approved') === '0', fn (Builder $q) => $q->where('approved', false));

        return Inertia::render('contractors/index', [
            'contractors' => TableQuery::paginate($query, $request, ['name', 'registration_no', 'contact_name', 'email'], ['name', 'insurance_expires_on', 'created_at'], 'name'),
            'filters' => TableQuery::filters($request, ['approved']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Contractor::create($this->validated($request));

        return $this->done(__('Contractor created.'));
    }

    public function update(Request $request, Contractor $contractor): RedirectResponse
    {
        $contractor->update($this->validated($request));

        return $this->done(__('Contractor updated.'));
    }

    /**
     * Approve or withdraw approval; a separate permission from editing.
     */
    public function toggleApproval(Contractor $contractor): RedirectResponse
    {
        $contractor->update(['approved' => ! $contractor->approved]);

        return $this->done($contractor->approved ? __('Contractor approved.') : __('Contractor approval withdrawn.'));
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        if ($contractor->workers()->exists()) {
            return $this->toast('error', __('This contractor still has workers. Withdraw approval instead.'));
        }

        $contractor->delete();

        return $this->done(__('Contractor deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:50'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'insurance_expires_on' => ['nullable', 'date'],
        ]);
    }
}
