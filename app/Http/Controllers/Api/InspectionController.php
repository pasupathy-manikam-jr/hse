<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\Inspection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Inspections from a checklist, filled in on site by the inspector who started them.
 */
class InspectionController extends Controller
{
    /**
     * Inspections at the person's sites, in progress first, then newest.
     *
     * @return LengthAwarePaginator<int, Inspection>
     */
    public function index(Request $request): LengthAwarePaginator
    {
        return Inspection::query()->visibleTo($this->user($request))->with('site:id,code', 'area:id,name', 'creator:id,name')
            ->orderByRaw("status = 'in-progress' desc")->latest()->paginate(30);
    }

    /** Start an inspection from an active checklist; its questions are copied in. */
    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'checklist_template_id' => ['required', Rule::exists('checklist_templates', 'id')->where('active', true)],
            'site_id' => ['required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('site_id', $request->integer('site_id'))],
        ]);

        $template = ChecklistTemplate::query()->with('items')->findOrFail($request->integer('checklist_template_id'));
        $inspection = Inspection::start($template, $request->integer('site_id'), isset($data['area_id']) ? $request->integer('area_id') : null);

        return response()->json($this->detail($inspection), 201);
    }

    /** One inspection with its questions, answers, photos and the actions raised. */
    public function show(Request $request, Inspection $inspection): Inspection
    {
        abort_unless($inspection->isVisibleTo($this->user($request)), 404);

        return $this->detail($inspection);
    }

    /**
     * Save answers (multipart): `answers[<answer id>][answer]` (yes/no/na, 1–5, a number or
     * text), `[notes]` and `[photos][]`, plus `notes`. Send `complete=1` to finish: every scored
     * question must be answered, and each failure raises an action for the inspector.
     */
    public function answers(Request $request, Inspection $inspection): Inspection
    {
        $user = $this->user($request);
        abort_unless($inspection->isVisibleTo($user), 404);
        $data = $request->validate(Inspection::answerRules());

        $inspection->saveAnswers($user, $data['answers'] ?? [], $data['notes'] ?? null,
            collect((array) $request->file('answers', []))->map(fn ($a) => $a['photos'] ?? [])->all());

        if ($request->boolean('complete')) {
            $inspection->complete($user);
        }

        return $this->detail($inspection);
    }

    private function detail(Inspection $inspection): Inspection
    {
        return $inspection->load('site:id,code,name', 'area:id,name', 'creator:id,name', 'answers.photos:id,photoable_type,photoable_id', 'actions.owner:id,name');
    }
}
