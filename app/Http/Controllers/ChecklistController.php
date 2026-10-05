<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checklist templates and their questions. Inspections copy the questions when they start,
 * so editing a template never changes past inspections.
 */
class ChecklistController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('checklists/index', [
            'templates' => TableQuery::paginate(ChecklistTemplate::query()->withCount('items'), $request, ['name', 'description'], ['name', 'created_at'], 'name'),
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = ChecklistTemplate::create($this->templateData($request));

        return to_route('checklists.show', $template);
    }

    public function show(ChecklistTemplate $checklist): Response
    {
        return Inertia::render('checklists/show', [
            'template' => $checklist->load('items'),
        ]);
    }

    public function update(Request $request, ChecklistTemplate $checklist): RedirectResponse
    {
        $checklist->update($this->templateData($request));

        return $this->done(__('Checklist updated.'));
    }

    public function destroy(ChecklistTemplate $checklist): RedirectResponse
    {
        $checklist->delete();

        return to_route('checklists.index');
    }

    public function storeItem(Request $request, ChecklistTemplate $checklist): RedirectResponse
    {
        $checklist->items()->create([...$this->itemData($request), 'sort' => (int) $checklist->items()->max('sort') + 1]);

        return $this->done(__('Question added.'));
    }

    public function updateItem(Request $request, ChecklistTemplate $checklist, ChecklistItem $item): RedirectResponse
    {
        abort_unless($item->checklist_template_id === $checklist->id, 404);
        $item->update($this->itemData($request));

        return $this->done(__('Question updated.'));
    }

    public function destroyItem(ChecklistTemplate $checklist, ChecklistItem $item): RedirectResponse
    {
        abort_unless($item->checklist_template_id === $checklist->id, 404);
        $item->delete();

        return $this->done(__('Question removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function templateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemData(Request $request): array
    {
        $type = $request->input('response_type');
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'response_type' => ['required', Rule::in(ChecklistItem::RESPONSE_TYPES)],
            // number: acceptable range; rating: the lowest passing score (1–5).
            'min' => [in_array($type, ['number', 'rating'], true) ? 'nullable' : 'prohibited', 'numeric', Rule::when($type === 'rating', 'between:1,5')],
            'max' => [$type === 'number' ? 'nullable' : 'prohibited', 'numeric', Rule::when($request->filled('min'), 'gte:min')],
            'critical' => ['boolean'],
        ]);

        return [...$data, 'min' => $data['min'] ?? null, 'max' => $data['max'] ?? null];
    }
}
