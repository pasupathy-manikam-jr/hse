<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthExam;
use App\Models\HealthProgramme;
use App\Models\HealthProgrammeMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Health surveillance in the app. Everyone gets their own record (`me/health`, USECHH reg. 27(6));
 * HSE managers (manage-health) see the programmes at their sites and record results and readings.
 * Setting programmes up and adding workers stay on the web.
 */
class HealthController extends Controller
{
    /**
     * The signed-in person's surveillance: the exposures they are monitored for and their results.
     * Download a report with GET /me/health/exams/{id}/file.
     *
     * @return array{memberships: mixed, exams: mixed}
     */
    public function mine(Request $request): array
    {
        $user = $this->user($request);

        return [
            'memberships' => HealthProgrammeMember::query()->where('user_id', $user->id)
                ->with('programme:id,name,hazard,site_id', 'programme.site:id,code')->orderByDesc('started_on')->get(),
            'exams' => HealthExam::query()->where('user_id', $user->id)->with('programme:id,name,hazard')
                ->orderByDesc('examined_on')->get(['id', 'health_programme_id', 'user_id', 'type', 'examined_on', 'provider', 'outcome', 'notes', 'file_name']),
        ];
    }

    /**
     * Programmes at the person's sites, with how many examinations are due or overdue and when
     * monitoring is next due.
     *
     * @return list<HealthProgramme>
     */
    public function index(Request $request): array
    {
        return array_values(HealthProgramme::query()->visibleTo($this->user($request))->where('active', true)
            ->with('site:id,code', 'chemical:id,name')->withCount(['members' => fn (Builder $q) => $q->whereNull('ended_on')])
            ->orderBy('name')->get()
            ->each(function (HealthProgramme $p) {
                $schedule = collect($p->schedule());
                $p->setAttribute('due_count', $schedule->whereIn('state', ['overdue', 'due-soon'])->count());
                $p->setAttribute('overdue_count', $schedule->where('state', 'overdue')->count());
                $p->setAttribute('monitoring_due_on', $p->monitoringDueOn()?->toDateString());
            })->all());
    }

    /**
     * One programme: `schedule` has each exposed worker's next examination (type, due date, state:
     * overdue, due-soon, ok or removed), `exams` the results with `notify_dosh`, `readings` the
     * monitoring, `outcomes` the results allowed for its hazard.
     *
     * @return array<string, mixed>
     */
    public function show(Request $request, HealthProgramme $programme): array
    {
        abort_unless($programme->isVisibleTo($this->user($request)), 404);

        return [
            'programme' => $programme->load('site:id,code,name', 'chemical:id,name'),
            'schedule' => $programme->schedule(),
            'exams' => $programme->exams()->with('user:id,name')->limit(200)->get(),
            'readings' => $programme->readings()->with('user:id,name')->get(),
            'monitoring_due_on' => $programme->monitoringDueOn()?->toDateString(),
            'outcomes' => $programme->outcomes(),
        ];
    }

    /**
     * Record a result (multipart, the report as `file`). The answer's `notify_dosh` says whether
     * DOSH must be told within 7 days; tell the worker within 21.
     */
    public function storeExam(Request $request, HealthProgramme $programme): JsonResponse
    {
        abort_unless($programme->isVisibleTo($this->user($request)), 404);

        return response()->json($programme->recordExam($request->validate($programme->examRules()), $request->file('file')), 201);
    }

    /** The worker has been told their result. */
    public function informed(Request $request, HealthProgramme $programme, HealthExam $exam): HealthExam
    {
        $this->ownExam($request, $programme, $exam);
        $exam->markInformed($request->validate($exam->informedRules())['informed_on']);

        return $exam;
    }

    /** DOSH has been notified of a notifiable result (422 on `status` when it is not notifiable). */
    public function notified(Request $request, HealthProgramme $programme, HealthExam $exam): HealthExam
    {
        $this->ownExam($request, $programme, $exam);
        $data = $request->validate($exam->notifiedRules());
        $exam->markDoshNotified($data['dosh_notified_on'], $data['dosh_reference']);

        return $exam;
    }

    /**
     * Record an exposure reading (multipart, the report as `file`); `over_limit` is true when it
     * is above the programme's exposure limit.
     */
    public function storeReading(Request $request, HealthProgramme $programme): JsonResponse
    {
        abort_unless($programme->isVisibleTo($this->user($request)), 404);
        $reading = $programme->recordReading($request->validate(HealthProgramme::readingRules()), $request->file('file'));

        return response()->json(['reading' => $reading, 'over_limit' => $programme->overLimit($reading)], 201);
    }

    private function ownExam(Request $request, HealthProgramme $programme, HealthExam $exam): void
    {
        abort_unless($programme->isVisibleTo($this->user($request)) && $exam->health_programme_id === $programme->id, 404);
    }
}
