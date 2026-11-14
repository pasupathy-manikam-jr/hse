<?php

namespace App\Http\Controllers;

use App\Models\Chemical;
use App\Models\ExposureReading;
use App\Models\HealthExam;
use App\Models\HealthProgramme;
use App\Models\HealthProgrammeMember;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Health surveillance: programmes per exposure (noise, chemicals), the exposed workers, their
 * examinations and the exposure monitoring. Medical results: HSE managers only (manage-health);
 * each worker sees their own under "My health records".
 */
class HealthController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $sites = Site::query()->availableTo($user)->get(['id', 'code', 'name']);
        $programmes = HealthProgramme::query()->visibleTo($user)->with('site:id,code', 'chemical:id,name')
            ->withCount(['members' => fn (Builder $q) => $q->whereNull('ended_on')])
            ->orderByDesc('active')->orderBy('name')->get()
            ->each(function (HealthProgramme $p) {
                $schedule = collect($p->schedule());
                $p->setAttribute('due_count', $schedule->whereIn('state', ['overdue', 'due-soon'])->count());
                $p->setAttribute('overdue_count', $schedule->where('state', 'overdue')->count());
                $p->setAttribute('monitoring_due_on', $p->monitoringDueOn()?->toDateString());
            });

        return Inertia::render('health/index', [
            'programmes' => $programmes,
            'sites' => $sites,
            'chemicals' => Chemical::query()->whereIn('site_id', $sites->pluck('id'))->orderBy('name')->get(['id', 'site_id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $programme = HealthProgramme::create($this->validated($request));

        return to_route('health.show', $programme);
    }

    public function show(Request $request, HealthProgramme $programme): Response
    {
        abort_unless($programme->isVisibleTo($this->user($request)), 404);
        $programme->load('site:id,code,name', 'chemical:id,name');

        return Inertia::render('health/show', [
            'programme' => $programme,
            'schedule' => $programme->schedule(),
            'exams' => $programme->exams()->with('user:id,name', 'creator:id,name')->limit(200)->get(),
            'readings' => $programme->readings()->with('user:id,name')->get(),
            'monitoringDueOn' => $programme->monitoringDueOn()?->toDateString(),
            'outcomes' => $programme->outcomes(),
            'chemicals' => Chemical::query()->where('site_id', $programme->site_id)->orderBy('name')->get(['id', 'site_id', 'name']),
            'people' => User::query()->where(fn (Builder $q) => $q->whereNull('site_id')->orWhere('site_id', $programme->site_id))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, HealthProgramme $programme): RedirectResponse
    {
        abort_unless($programme->isVisibleTo($this->user($request)), 404);
        $programme->update(Arr::except($this->validated($request, $programme), 'site_id'));

        return $this->done(__('Programme updated.'));
    }

    /** Add a worker to the programme from the day their exposure began. */
    public function storeMember(Request $request, HealthProgramme $programme): RedirectResponse
    {
        abort_unless($programme->isVisibleTo($this->user($request)), 404);
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $programme->site_id)),
                Rule::unique('health_programme_members')->where('health_programme_id', $programme->id)],
            'started_on' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $programme->members()->create($data);

        return $this->done(__('Worker added.'));
    }

    /** The worker no longer has the exposure (moved job, left). Their records stay. */
    public function endMember(Request $request, HealthProgramme $programme, HealthProgrammeMember $member): RedirectResponse
    {
        abort_unless($programme->isVisibleTo($this->user($request)) && $member->health_programme_id === $programme->id, 404);
        $member->update($request->validate(['ended_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$member->started_on->toDateString()]]));

        return $this->done(__('Exposure ended.'));
    }

    /** Record an examination result (multipart: the report as `file`). */
    public function storeExam(Request $request, HealthProgramme $programme): RedirectResponse
    {
        abort_unless($programme->isVisibleTo($this->user($request)), 404);
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('health_programme_members', 'user_id')->where('health_programme_id', $programme->id)],
            'type' => ['required', Rule::in(HealthExam::TYPES)],
            'examined_on' => ['required', 'date', 'before_or_equal:today'],
            'provider' => ['required', 'string', 'max:255'],
            'outcome' => ['required', Rule::in($programme->outcomes())],
            'notes' => ['nullable', 'string', 'max:5000'],
            'file' => HealthExam::uploadRules(),
        ]);

        $exam = new HealthExam(Arr::except($data, 'file'));
        $exam->programme()->associate($programme);

        if ($request->hasFile('file')) {
            $exam->attachUpload($request->file('file'));
        }

        $exam->save();

        return $this->done($exam->notify_dosh
            ? __('Result recorded. Notify DOSH within :n days, and tell the worker.', ['n' => HealthExam::NOTIFY_DAYS])
            : __('Result recorded. Tell the worker within :n days.', ['n' => HealthExam::INFORM_DAYS]));
    }

    /** The worker has been told their result. */
    public function informed(Request $request, HealthProgramme $programme, HealthExam $exam): RedirectResponse
    {
        $this->ownExam($request, $programme, $exam);
        $exam->forceFill($request->validate(['informed_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$exam->examined_on->toDateString()]]))->save();

        return $this->done(__('Recorded.'));
    }

    /** DOSH has been notified of a notifiable result. */
    public function notified(Request $request, HealthProgramme $programme, HealthExam $exam): RedirectResponse
    {
        $this->ownExam($request, $programme, $exam);

        if (! $exam->notify_dosh) {
            throw ValidationException::withMessages(['status' => __('This result is not notifiable.')]);
        }

        $exam->forceFill($request->validate([
            'dosh_notified_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$exam->examined_on->toDateString()],
            'dosh_reference' => ['required', 'string', 'max:100'],
        ]))->save();

        return $this->done(__('DOSH notification recorded.'));
    }

    public function storeReading(Request $request, HealthProgramme $programme): RedirectResponse
    {
        abort_unless($programme->isVisibleTo($this->user($request)), 404);
        $data = $request->validate([
            'measured_on' => ['required', 'date', 'before_or_equal:today'],
            'value' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'user_id' => ['nullable', Rule::exists('users', 'id')],
            'assessor' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'file' => ExposureReading::uploadRules(),
        ]);

        $reading = new ExposureReading(Arr::except($data, 'file'));
        $reading->programme()->associate($programme);

        if ($request->hasFile('file')) {
            $reading->attachUpload($request->file('file'));
        }

        $reading->save();

        $over = $programme->exposure_limit !== null && (float) $reading->value > (float) $programme->exposure_limit;

        return $over
            ? $this->toast('error', __('Reading recorded: above the exposure limit. Review the controls.'))
            : $this->done(__('Reading recorded.'));
    }

    /** An examination report: for HSE managers at the site, or the worker it is about. */
    public function examFile(Request $request, HealthExam $exam): StreamedResponse
    {
        $user = $this->user($request);
        abort_unless($exam->user_id === $user->id || ($user->can('manage-health') && $exam->programme->isVisibleTo($user)), 404);

        return $exam->downloadUpload();
    }

    public function readingFile(Request $request, ExposureReading $reading): StreamedResponse
    {
        abort_unless($reading->programme->isVisibleTo($this->user($request)), 404);

        return $reading->downloadUpload();
    }

    /**
     * The signed-in worker's own surveillance: programmes they are in, and their results
     * (USECHH reg. 27(6): an employee may see their own record).
     */
    public function mine(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('health/mine', [
            'memberships' => HealthProgrammeMember::query()->where('user_id', $user->id)
                ->with('programme:id,name,hazard,site_id', 'programme.site:id,code')->orderByDesc('started_on')->get(),
            'exams' => HealthExam::query()->where('user_id', $user->id)->with('programme:id,name,hazard')
                ->orderByDesc('examined_on')->get(['id', 'health_programme_id', 'user_id', 'type', 'examined_on', 'provider', 'outcome', 'notes', 'file_name']),
        ]);
    }

    private function ownExam(Request $request, HealthProgramme $programme, HealthExam $exam): void
    {
        abort_unless($programme->isVisibleTo($this->user($request)) && $exam->health_programme_id === $programme->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?HealthProgramme $programme = null): array
    {
        $user = $this->user($request);
        $siteId = $programme->site_id ?? $request->integer('site_id');

        return $request->validate([
            'site_id' => [$programme ? 'nullable' : 'required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'name' => ['required', 'string', 'max:255'],
            'hazard' => [$programme ? 'prohibited' : 'required', Rule::in(HealthProgramme::HAZARDS)],
            'chemical_id' => ['nullable', Rule::exists('chemicals', 'id')->where('site_id', $siteId)],
            'schedule_ii' => ['boolean'],
            // USECHH reg. 27(3): no longer than 12 months for a Schedule II chemical; audiometry is yearly.
            'exam_interval_months' => ['required', 'integer', 'between:1,'.($request->boolean('schedule_ii') || ($programme->hazard ?? $request->input('hazard')) === 'noise' ? 12 : 60)],
            'monitoring_interval_months' => ['nullable', 'integer', 'between:1,'.($request->boolean('schedule_ii') ? 6 : 60)],
            'exposure_limit' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'active' => ['boolean'],
        ]);
    }
}
