<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\Permit;
use App\Models\User;
use App\Models\UserCompetency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The training matrix: people (at the user's sites) × competencies, from each person's latest record.
 */
class CompetencyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $people = User::query()
            ->when($user->site_id !== null, fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('site_id')->orWhere('site_id', $user->site_id)))
            ->when($request->filled('search'), fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes($request->string('search')->toString(), '%_\\').'%'))
            ->with(['competencies' => fn ($q) => $q->orderByDesc('issued_on')->orderByDesc('id')])
            ->orderBy('name')->get(['id', 'name', 'contractor_id']);

        return Inertia::render('competencies/index', [
            'competencies' => Competency::query()->orderBy('name')->get(),
            'people' => $people->map(fn (User $p) => [
                'id' => $p->id,
                'name' => $p->name,
                // Latest record per competency.
                'records' => $p->competencies->unique('competency_id')->values()
                    ->map(fn (UserCompetency $c) => Arr::only($c->toArray(), ['id', 'competency_id', 'issued_on', 'expires_on', 'reference']) + ['has_file' => $c->file_path !== null]),
            ]),
            'permitTypes' => Permit::TYPES,
            'expiringDays' => UserCompetency::EXPIRING_DAYS,
            'filters' => $request->only('search'),
        ]);
    }

    public function storeCompetency(Request $request): RedirectResponse
    {
        Competency::create($this->competencyData($request));

        return $this->done(__('Competency added.'));
    }

    public function updateCompetency(Request $request, Competency $competency): RedirectResponse
    {
        $competency->update($this->competencyData($request, $competency));

        return $this->done(__('Competency updated.'));
    }

    /**
     * Record that someone holds a competency; the expiry follows from its validity unless given.
     */
    public function storeRecord(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'competency_id' => ['required', Rule::exists('competencies', 'id')],
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'expires_on' => ['nullable', 'date', 'after:issued_on'],
            'reference' => ['nullable', 'string', 'max:100'],
            'file' => UserCompetency::uploadRules(),
        ]);

        $competency = Competency::query()->findOrFail($request->integer('competency_id'));
        $record = new UserCompetency([
            'user_id' => $data['user_id'],
            'competency_id' => $competency->id,
            'issued_on' => $data['issued_on'],
            'expires_on' => $data['expires_on'] ?? ($competency->validity_months ? Carbon::parse($data['issued_on'])->addMonths($competency->validity_months)->toDateString() : null),
            'reference' => $data['reference'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $record->attachUpload($request->file('file'));
        }

        $record->save();

        return $this->done(__('Training recorded.'));
    }

    public function destroyRecord(UserCompetency $record): RedirectResponse
    {
        $record->delete();

        return $this->done(__('Record removed.'));
    }

    public function file(UserCompetency $record): StreamedResponse
    {
        return $record->downloadUpload();
    }

    /**
     * @return array<string, mixed>
     */
    private function competencyData(Request $request, ?Competency $competency = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('competencies')->ignore($competency)],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'permit_type' => ['nullable', Rule::in(Permit::TYPES)],
        ]);
    }
}
