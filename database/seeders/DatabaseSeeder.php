<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\ChecklistTemplate;
use App\Models\Chemical;
use App\Models\Competency;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\Incident;
use App\Models\Inspection;
use App\Models\InternalAudit;
use App\Models\IsoClause;
use App\Models\Observation;
use App\Models\Permit;
use App\Models\RiskAssessment;
use App\Models\ShiftHandover;
use App\Models\Site;
use App\Models\SiteMetric;
use App\Models\ToolboxTalk;
use App\Models\User;
use App\Models\UserCompetency;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * One demo account per role, <role>@example.com, all with the DEMO_PASSWORD password.
     * With DEMO_LOGINS=true the login page also offers them as one-click logins.
     *
     * @return list<array{name: string, email: string, password: string}>
     */
    public static function logins(): array
    {
        return array_map(fn (string $role) => [
            'name' => RolesSeeder::label($role),
            'email' => "{$role}@example.com",
            'password' => (string) config('app.demo_password'),
        ], array_keys(RolesSeeder::ROLES));
    }

    /**
     * Seed the application's database. Safe to run more than once. Model events stay on,
     * so seeded records get their creators and audit trail like any other.
     */
    public function run(): void
    {
        $this->call([RolesSeeder::class, ChecklistSeeder::class, IsoClauseSeeder::class]);

        // The two demo profiles from PLAN.md: a construction site and a manufacturing plant.
        $sites = [
            'KL-TWR' => ['Menara Damai tower project', 'Jalan Ampang, Kuala Lumpur', ['Basement', 'Block A', 'Crane zone', 'Site office']],
            'SHA-PLT' => ['Shah Alam stamping plant', 'Seksyen 15, Shah Alam', ['Press line', 'Warehouse', 'Boiler house', 'Loading bay']],
        ];

        foreach ($sites as $code => [$name, $address, $areas]) {
            $site = Site::query()->firstOrCreate(['code' => $code], ['name' => $name, 'address' => $address]);

            foreach ($areas as $area) {
                $site->areas()->firstOrCreate(['name' => $area]);
            }
        }

        $contractor = Contractor::query()->firstOrCreate(['name' => 'Bina Scaffold Sdn Bhd'], [
            'registration_no' => '201901012345', 'contact_name' => 'Ahmad Razak', 'email' => 'safety@binascaffold.example',
            'phone' => '+60 3-1234 5678', 'insurance_expires_on' => now()->addMonths(8)->toDateString(), 'approved' => true,
        ]);

        // One demo account per role, see logins(). Site staff work at the tower project.
        $tower = Site::query()->where('code', 'KL-TWR')->value('id');

        foreach (self::logins() as ['name' => $name, 'email' => $email, 'password' => $password]) {
            $role = Str::before($email, '@');
            User::query()->firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => Hash::make($password), 'email_verified_at' => now(),
                'site_id' => in_array($role, ['supervisor', 'permit-issuer', 'worker'], true) ? $tower : null,
                'contractor_id' => $role === 'worker' ? $contractor->id : null,
            ])->syncRoles([$role]);
        }

        $this->observations($tower);
        $this->incidents($tower, (int) Site::query()->where('code', 'SHA-PLT')->value('id'));
        $this->riskAndInspections($tower);
        $this->trainingAndPermits($tower);
        $this->kpisDocumentsAndAudits();
        $this->environmentAndChemicals($tower);
    }

    /**
     * Nine months of environmental figures at the tower, an approved COSHH assessment, and three
     * chemicals: one covered, one missing its COSHH assessment, one with an old SDS.
     */
    private function environmentAndChemicals(int $towerId): void
    {
        if (Chemical::query()->exists()) {
            return;
        }

        $user = fn (string $role) => User::query()->where('email', "{$role}@example.com")->firstOrFail();
        $area = fn (string $name) => Area::query()->where(['site_id' => $towerId, 'name' => $name])->value('id');

        foreach (range(0, 8) as $n) {
            $month = now()->startOfMonth()->subMonths($n)->toDateString();
            foreach (['general-waste' => 18 + $n, 'hazardous-waste' => 1.2, 'recycled-waste' => 9 + ($n % 4), 'water' => 640 + 20 * $n, 'electricity' => 31_000 + 500 * $n, 'diesel' => 4_200 - 100 * $n] as $metric => $value) {
                SiteMetric::query()->updateOrCreate(['site_id' => $towerId, 'month' => $month, 'metric' => $metric], ['value' => $value]);
            }
        }

        auth()->setUser($user('supervisor'));
        $coshh = RiskAssessment::create([
            'site_id' => $towerId, 'area_id' => $area('Basement'), 'type' => 'coshh', 'title' => 'Concrete curing compounds and release agents',
            'activity' => 'Spraying curing compound on new slabs and applying release agent to formwork.', 'review_due_on' => now()->addYear()->toDateString(),
        ]);
        $coshh->hazards()->create(['hazard' => 'Skin and eye irritation from spray', 'who_at_risk' => 'Concrete gang', 'likelihood' => 3, 'severity' => 2,
            'additional_controls' => 'Nitrile gloves, goggles, low-pressure sprayer', 'residual_likelihood' => 2, 'residual_severity' => 2]);
        $coshh->approve($user('hse-manager'));

        foreach ([
            ['Curing compound CC-200', 'Bina Chem Sdn Bhd', ['harmful', 'environment'], 400, 'L', 'Basement', now()->subYears(2), $coshh->id],
            ['Diesel (site generators)', 'Petro Supply', ['flammable', 'harmful', 'health-hazard', 'environment'], 5000, 'L', 'Site office', now()->subYear(), null],
            ['Acetylene cylinders', 'Gas Industries', ['flammable', 'gas-under-pressure'], 6, 'cyl', 'Crane zone', now()->subYears(6), null],
        ] as [$name, $supplier, $hazards, $quantity, $unit, $areaName, $sdsDate, $raId]) {
            $chemical = new Chemical([
                'site_id' => $towerId, 'area_id' => $area($areaName), 'name' => $name, 'supplier' => $supplier, 'hazards' => $hazards,
                'max_quantity' => $quantity, 'unit' => $unit, 'sds_issued_on' => $sdsDate->toDateString(), 'risk_assessment_id' => $raId,
            ]);
            // A placeholder SDS so the demo links work; real ones are uploaded from the register.
            $chemical->attachUpload(UploadedFile::fake()->create(Str::slug($name).'-sds.pdf', 30, 'application/pdf'));
            $chemical->save();
        }

        auth()->forgetUser();
    }

    /**
     * Twelve months of hours at each site (so the rates show), an HSE policy in force that the
     * worker must read, a procedure being revised, and an audit in progress with a nonconformity.
     */
    private function kpisDocumentsAndAudits(): void
    {
        if (Document::query()->exists()) {
            return;
        }

        $user = fn (string $role) => User::query()->where('email', "{$role}@example.com")->firstOrFail();
        $clause = fn (string $number) => (int) IsoClause::query()->where('number', $number)->value('id');

        foreach (['KL-TWR' => 42_000, 'SHA-PLT' => 26_500] as $code => $hours) {
            $siteId = (int) Site::query()->where('code', $code)->value('id');

            foreach (range(0, 11) as $n) {
                SiteMetric::query()->updateOrCreate(
                    ['site_id' => $siteId, 'month' => now()->startOfMonth()->subMonths($n)->toDateString(), 'metric' => SiteMetric::HOURS],
                    ['value' => $hours + ($n % 3) * 1_500],
                );
            }
        }

        auth()->setUser($user('hse-manager'));
        $policy = Document::create(['number' => 'HSE-POL-001', 'title' => 'Health, safety and environment policy', 'type' => 'policy',
            'owner_id' => $user('hse-manager')->id, 'review_interval_months' => 12]);
        $policy->clauses()->sync([$clause('5.2'), $clause('5.1')]);
        $revision = $policy->startRevision();
        $revision->forceFill(['change_summary' => 'First issue.', 'status' => 'effective', 'approved_by' => $user('admin')->id, 'approved_at' => now()->subMonths(11)])->save();
        $policy->forceFill(['next_review_on' => now()->addDays(20)->toDateString()])->save();
        $revision->readers()->sync([$user('worker')->id, $user('supervisor')->id => ['acknowledged_at' => now()->subMonths(10)]]);

        $lifting = Document::create(['number' => 'HSE-PRO-004', 'title' => 'Lifting operations with tower cranes', 'type' => 'procedure',
            'owner_id' => $user('supervisor')->id, 'review_interval_months' => 24]);
        $lifting->clauses()->sync([$clause('8.1.2')]);
        $lifting->startRevision()->update(['change_summary' => 'Adds the swing-radius barricade after the rebar near miss.']);

        $audit = InternalAudit::create([
            'site_id' => (int) Site::query()->where('code', 'KL-TWR')->value('id'), 'title' => 'Q4 OH&S audit: tower project',
            'scope' => 'Operational control and competence on site.', 'lead_auditor_id' => $user('auditor')->id, 'planned_on' => now()->subDays(2)->toDateString(),
        ]);
        $audit->clauses()->sync([$clause('7.2'), $clause('8.1.2'), $clause('8.1.4')]);
        $audit->start();
        $audit->addFinding(['type' => 'minor-nonconformity', 'iso_clause_id' => $clause('7.2'), 'description' => 'One scaffolder on the level 6 crew had no record of scaffold inspection training.'],
            ['owner_id' => $user('supervisor')->id, 'due_on' => now()->addWeeks(2)->toDateString()]);
        $audit->addFinding(['type' => 'observation', 'iso_clause_id' => $clause('8.1.4'), 'description' => 'Contractor insurance copies are kept on paper only; consider scanning them into the system.']);
        auth()->forgetUser();
    }

    /**
     * Competencies (some tied to permit types), training records with one expired and one
     * expiring, a toolbox talk on the crush injury, and three permits: one active, one blocked
     * by an expired ticket, and a hot-work request that conflicts with it.
     */
    private function trainingAndPermits(int $towerId): void
    {
        if (Competency::query()->exists()) {
            return;
        }

        $user = fn (string $role) => User::query()->where('email', "{$role}@example.com")->firstOrFail();
        $area = fn (string $name) => (int) Area::query()->where(['site_id' => $towerId, 'name' => $name])->value('id');
        foreach ([
            ['Confined space entry', 24, 'confined-space'], ['Hot work', 12, 'hot-work'], ['Working at height', 36, 'work-at-height'],
            ['Electrical isolation', 36, 'electrical'], ['Rigging and slinging', 36, 'lifting'], ['First aid', 36, null],
        ] as [$name, $months, $type]) {
            Competency::create(['name' => $name, 'validity_months' => $months, 'permit_type' => $type]);
        }

        $record = fn (string $role, string $name, string $issued, string $expires) => UserCompetency::create([
            'user_id' => $user($role)->id, 'competency_id' => Competency::query()->where('name', $name)->value('id'), 'issued_on' => $issued, 'expires_on' => $expires,
        ]);
        $record('worker', 'Working at height', now()->subYear()->toDateString(), now()->addYears(2)->toDateString());
        $record('worker', 'Confined space entry', now()->subYears(2)->subMonth()->toDateString(), now()->subMonth()->toDateString());
        $record('worker', 'Hot work', now()->subMonths(3)->toDateString(), now()->addMonths(9)->toDateString());
        $record('supervisor', 'First aid', now()->subYears(3)->addDays(20)->toDateString(), now()->addDays(20)->toDateString());
        $record('supervisor', 'Working at height', now()->subMonths(6)->toDateString(), now()->addMonths(30)->toDateString());

        auth()->setUser($user('supervisor'));
        $talk = ToolboxTalk::create([
            'site_id' => $towerId, 'topic' => 'Passing scaffold tubes: use the gin wheel', 'held_on' => now()->subDays(10)->toDateString(),
            'presenter_id' => $user('supervisor')->id, 'incident_id' => Incident::query()->where('title', 'Hand crushed between scaffold tubes')->value('id'),
            'notes' => 'No hand passing above two lifts. One gin wheel per active bay.',
        ]);
        $talk->attendees()->sync([$user('worker')->id, $user('permit-issuer')->id]);

        $scaffoldRa = RiskAssessment::query()->where('title', 'Erecting and dismantling scaffold')->where('status', 'approved')->firstOrFail();
        $precautions = fn (string $type) => array_fill_keys(array_keys(Permit::precautionsFor($type)), true);

        // Requested by the supervisor, approved (signed) by the permit issuer, then started.
        $height = Permit::create([
            'type' => 'work-at-height', 'site_id' => $towerId, 'area_id' => $area('Block A'), 'risk_assessment_id' => $scaffoldRa->id,
            'description' => 'Fix guardrails on the level 7 east edge.', 'valid_from' => now()->subHour(), 'valid_to' => now()->addHours(7),
            'precautions' => $precautions('work-at-height'),
        ]);
        $height->workers()->sync([$user('worker')->id]);
        $height->transitionTo('approved', $user('permit-issuer'), password: (string) config('app.demo_password'));
        $height->transitionTo('active', $user('supervisor'));

        // Blocked: the worker's confined-space ticket has expired and no gas test is recorded.
        $tank = Permit::create([
            'type' => 'confined-space', 'site_id' => $towerId, 'area_id' => $area('Basement'), 'risk_assessment_id' => $scaffoldRa->id,
            'description' => 'Inspect the sump pit before waterproofing.', 'valid_from' => now()->addHours(2), 'valid_to' => now()->addHours(6),
            'precautions' => $precautions('confined-space'),
        ]);
        $tank->workers()->sync([$user('worker')->id]);

        // Hot work in the basement at the same time: flagged as a conflict on both permits.
        $weld = Permit::create([
            'type' => 'hot-work', 'site_id' => $towerId, 'area_id' => $area('Basement'), 'risk_assessment_id' => $scaffoldRa->id,
            'description' => 'Weld brackets for the sump pump.', 'valid_from' => now()->addHours(3), 'valid_to' => now()->addHours(5),
        ]);
        $weld->workers()->sync([$user('worker')->id]);

        // The day supervisor hands over to the permit issuer: the active height permit is captured.
        ShiftHandover::create([
            'site_id' => $towerId, 'shift' => 'day', 'to_user_id' => $user('permit-issuer')->id,
            'notes' => 'Guardrail fix on level 7 east edge still in progress. Wet boards on level 6 scaffold: keep off until dry.',
        ]);
        auth()->forgetUser();
    }

    /**
     * An approved scaffold assessment (flagged for review by the crush injury), a draft JSA,
     * a completed site walk with failures, and an inspection still in progress.
     */
    private function riskAndInspections(int $towerId): void
    {
        if (RiskAssessment::query()->exists()) {
            return;
        }

        $user = fn (string $role) => User::query()->where('email', "{$role}@example.com")->firstOrFail();
        $area = fn (string $name) => Area::query()->where(['site_id' => $towerId, 'name' => $name])->value('id');

        // Written by the supervisor, approved by the HSE manager (no self-approval).
        auth()->setUser($user('supervisor'));
        $scaffold = RiskAssessment::create([
            'site_id' => $towerId, 'area_id' => $area('Block A'), 'type' => 'hira', 'title' => 'Erecting and dismantling scaffold',
            'activity' => 'Erecting, altering and dismantling tube-and-fitting scaffold up to level 8, including lifting materials between lifts.',
            'review_due_on' => now()->addMonths(6)->toDateString(),
        ]);
        foreach ([
            ['Fall from height while erecting', 'Scaffolders', 'Harness worn', 4, 5, 'Advance guardrail system; rescue plan briefed', 2, 5],
            ['Falling tubes and fittings', 'Workers and passers-by below', 'Exclusion zone tape', 3, 4, 'Gin wheel for lifting; netting on the lift below; hard barriers', 2, 3],
            ['Hand injury passing tubes', 'Scaffolders', 'Gloves', 3, 3, 'Gin wheel per bay; no hand passing above two lifts', 2, 2],
            ['Scaffold collapse', 'Everyone on and near it', 'Design by competent person', 2, 5, 'Weekly inspection and tag; ties checked after high winds', 1, 5],
        ] as [$hazard, $who, $existing, $l, $s, $additional, $rl, $rs]) {
            $scaffold->hazards()->create([
                'hazard' => $hazard, 'who_at_risk' => $who, 'existing_controls' => $existing, 'likelihood' => $l, 'severity' => $s,
                'additional_controls' => $additional, 'residual_likelihood' => $rl, 'residual_severity' => $rs,
            ]);
        }
        $scaffold->approve($user('hse-manager'));

        // The crush injury happened under this assessment: linking it flags the assessment for review.
        Incident::query()->where('title', 'Hand crushed between scaffold tubes')->first()?->update(['risk_assessment_id' => $scaffold->id]);

        $jsa = RiskAssessment::create([
            'site_id' => $towerId, 'area_id' => $area('Crane zone'), 'type' => 'jsa', 'title' => 'Tower crane lifting operations',
            'activity' => 'Slinging and lifting rebar bundles and formwork with the tower crane.',
            'review_due_on' => now()->addYear()->toDateString(),
        ]);
        $jsa->hazards()->create([
            'hazard' => 'Load falling from sling', 'who_at_risk' => 'Riggers, people under the swing radius', 'existing_controls' => 'Certified slings',
            'likelihood' => 3, 'severity' => 5, 'additional_controls' => 'Barricade swing radius; banksman; tag lines',
            'residual_likelihood' => 2, 'residual_severity' => 5,
        ]);

        // A site walk by the supervisor with two failures, then completed: it raises two actions.
        $walk = Inspection::start(ChecklistTemplate::query()->where('name', 'Daily site walk')->with('items')->firstOrFail(), $towerId, $area('Block A'));
        $answers = ['yes', 'no', 'yes', 'no', 'yes', 'Concrete pour on level 5 this afternoon.'];
        $walk->answers->each(fn ($answer, $n) => $answer->update([
            'answer' => $answers[$n],
            'notes' => match ($n) {
                1 => 'Level 7 east edge: guardrail removed for a delivery and not refitted.',
                3 => 'Two labourers without safety glasses at the cutting station.',
                default => null,
            },
        ]));
        $walk->complete($user('supervisor'));

        // A fire extinguisher round still in progress.
        auth()->setUser($user('permit-issuer'));
        Inspection::start(ChecklistTemplate::query()->where('name', 'Fire extinguisher check')->with('items')->firstOrFail(), $towerId, $area('Site office'));
        auth()->forgetUser();
    }

    /**
     * Demo incidents: an investigated lost-time injury with an open action, a damage-only
     * incident just reported, and a closed first-aid case.
     */
    private function incidents(int $towerId, int $plantId): void
    {
        if (Incident::query()->exists()) {
            return;
        }

        $user = fn (string $role) => User::query()->where('email', "{$role}@example.com")->firstOrFail();
        $area = fn (int $siteId, string $name) => Area::query()->where(['site_id' => $siteId, 'name' => $name])->value('id');

        $crush = Incident::create([
            'site_id' => $towerId, 'area_id' => $area($towerId, 'Block A'), 'type' => 'injury',
            'title' => 'Hand crushed between scaffold tubes',
            'description' => 'While passing a tube up to level 6, the scaffolder\'s left hand was caught between the tube and a standard.',
            'immediate_actions' => 'First aid given, taken to the clinic for an X-ray. Lifting by hand stopped on that bay.',
            'occurred_at' => now()->subDays(12)->setTime(10, 40),
        ]);
        $crush->people()->create(['user_id' => $user('worker')->id, 'name' => $user('worker')->name, 'job_title' => 'Scaffolder', 'role' => 'injured',
            'treatment' => 'lost-time', 'injury_nature' => 'Fracture', 'body_part' => 'Left hand', 'days_lost' => 3, 'days_restricted' => 10]);
        $crush->people()->create(['user_id' => $user('supervisor')->id, 'name' => $user('supervisor')->name, 'job_title' => 'Site supervisor', 'role' => 'witness']);
        $crush->update([
            'investigation_team' => 'Supervisor, HSE manager, scaffold foreman',
            'sequence_of_events' => 'Tubes were passed hand to hand between lifts because the gin wheel was in use on another bay.',
            'whys' => ['Hand was between the tube and the standard.', 'Tubes were passed by hand between lifts.', 'The gin wheel was being used on another bay.', 'Only one gin wheel was on site for three bays.'],
            'root_cause_category' => 'equipment', 'root_cause' => 'Not enough lifting gear for the number of scaffold bays being erected at once.',
        ]);
        $crush->transitionTo('under-investigation');
        $crush->raiseAction(['description' => 'Provide one gin wheel per active scaffold bay.', 'control_level' => 'engineering', 'priority' => 'high',
            'owner_id' => $user('supervisor')->id, 'due_on' => now()->addWeek()->toDateString()]);
        $crush->transitionTo('actions-in-progress');

        Incident::create([
            'site_id' => $plantId, 'area_id' => $area($plantId, 'Warehouse'), 'type' => 'property-damage',
            'title' => 'Forklift struck racking upright',
            'description' => 'A reversing forklift hit the upright of racking bay 4. The upright is bent; the bay has been offloaded.',
            'occurred_at' => now()->subDays(2)->setTime(15, 5),
        ]);

        $cut = Incident::create([
            'site_id' => $towerId, 'area_id' => $area($towerId, 'Basement'), 'type' => 'injury',
            'title' => 'Cut finger on rebar tie wire',
            'description' => 'Steel fixer cut a finger on the end of a tie wire while fixing basement slab rebar.',
            'immediate_actions' => 'Cleaned and dressed at the first aid station.',
            'occurred_at' => now()->subDays(30)->setTime(8, 20),
        ]);
        $cut->people()->create(['name' => 'Rahim bin Osman', 'job_title' => 'Steel fixer (subcontractor)', 'role' => 'injured',
            'treatment' => 'first-aid', 'injury_nature' => 'Laceration', 'body_part' => 'Right index finger']);
        $cut->transitionTo('closed');
    }

    /**
     * Demo observations at the tower project, with actions at each stage.
     */
    private function observations(int $siteId): void
    {
        $area = fn (string $name) => Area::query()->where(['site_id' => $siteId, 'name' => $name])->value('id');
        $user = fn (string $role) => User::query()->where('email', "{$role}@example.com")->value('id');

        /** @var list<array{string, string, string, string, string|null, string, bool, list<array{string, string, string, string, int, string}>}> $rows */
        $rows = [
            ['near-miss', 'high', 'Crane zone', 'A bundle of rebar slipped from the sling during a lift and landed about 2 m from a labourer walking under the load.', 'Stopped lifting. Re-briefed the rigger on the exclusion zone.', 'worker', false, [
                ['Barricade the full swing radius of the tower crane during every lift.', 'engineering', 'high', 'supervisor', 3, 'done'],
                ['Re-train riggers and signallers on sling selection for loose bundles.', 'administrative', 'medium', 'permit-issuer', 10, 'open'],
            ]],
            ['unsafe-condition', 'medium', 'Block A', 'Edge protection missing on the level 7 slab edge, east side.', null, 'worker', true, []],
            ['unsafe-act', 'medium', 'Basement', 'Two workers grinding without face shields.', 'Stopped the work and issued face shields.', 'supervisor', false, [
                ['Add face shields to the grinding permit checklist.', 'administrative', 'low', 'hse-manager', 7, 'verified'],
            ]],
            ['positive', 'low', 'Site office', 'Scaffold crew did a full harness check before starting, without being asked.', null, 'supervisor', false, []],
        ];

        foreach ($rows as $i => [$type, $potential, $areaName, $description, $immediate, $reporter, $anonymous, $actions]) {
            if (Observation::query()->where('description', $description)->exists()) {
                continue;
            }

            $observation = Observation::create([
                'site_id' => $siteId, 'area_id' => $area($areaName), 'type' => $type, 'potential' => $potential,
                'description' => $description, 'immediate_action' => $immediate, 'observed_at' => now()->subDays(6 - $i)->setTime(9 + $i, 15),
                'anonymous' => $anonymous, 'reporter_id' => $anonymous ? null : $user($reporter),
            ]);

            foreach ($actions as [$what, $control, $priority, $owner, $dueInDays, $status]) {
                $action = $observation->raiseAction([
                    'description' => $what, 'control_level' => $control, 'priority' => $priority,
                    'owner_id' => $user($owner), 'due_on' => now()->addDays($dueInDays)->toDateString(),
                ]);
                $ownerUser = User::query()->findOrFail($action->owner_id);

                if ($status !== 'open') {
                    $action->transitionTo('done', $ownerUser, 'Done and photographed on site.');
                }

                if ($status === 'verified') {
                    $action->transitionTo('verified', User::query()->where('email', 'admin@example.com')->firstOrFail());
                }
            }

            if ($type === 'positive') {
                $observation->transitionTo('closed');
            }
        }
    }
}
