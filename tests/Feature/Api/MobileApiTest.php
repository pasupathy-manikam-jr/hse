<?php

use App\Models\Action;
use App\Models\ChecklistTemplate;
use App\Models\Equipment;
use App\Models\HealthProgramme;
use App\Models\Inspection;
use App\Models\Observation;
use App\Models\Permit;
use App\Models\RiskAssessment;
use App\Models\Site;
use App\Models\User;
use App\Notifications\HseAlert;
use Database\Seeders\ChecklistSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->area = $this->site->areas()->create(['name' => 'Crane zone']);
    $this->other = Site::create(['code' => 'SA', 'name' => 'Plant']);
});

/**
 * A user with a role, working at the tower, signed in to the API.
 */
function apiUser(string $role): User
{
    $user = test()->userWithRole($role);
    $user->update(['site_id' => test()->site->id]);
    Sanctum::actingAs($user);

    return $user;
}

test('the app signs in with a token, and two-factor users must also give a code', function () {
    $this->seed(RolesSeeder::class);
    $user = User::factory()->create(['email' => 'ali@example.com', 'password' => Hash::make('Zx123456')])->assignRole('worker');

    $this->postJson('/api/v1/auth/login', ['email' => 'ali@example.com', 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'ali@example.com', 'password' => 'Zx123456', 'device_name' => 'Pixel 8'])
        ->assertOk()->assertJsonPath('user.role', 'worker')->json('token');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('email', 'ali@example.com')
        ->assertJsonPath('permissions', fn (array $p) => in_array('create-observations', $p, true) && ! in_array('manage-permits', $p, true));
    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
    expect($user->tokens()->count())->toBe(0);

    $secret = (new Google2FA)->generateSecretKey();
    $user->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_recovery_codes' => encrypt(json_encode(['rescue-1'])), 'two_factor_confirmed_at' => now()])->save();
    $login = ['email' => 'ali@example.com', 'password' => 'Zx123456'];

    $this->postJson('/api/v1/auth/login', $login)->assertJsonValidationErrors('code');
    $this->postJson('/api/v1/auth/login', [...$login, 'code' => '000000'])->assertJsonValidationErrors('code');
    $this->postJson('/api/v1/auth/login', [...$login, 'code' => (new Google2FA)->getCurrentOtp($secret)])->assertOk();
    $this->postJson('/api/v1/auth/login', [...$login, 'recovery_code' => 'rescue-1'])->assertOk();
    $this->postJson('/api/v1/auth/login', [...$login, 'recovery_code' => 'rescue-1'])->assertJsonValidationErrors('recovery_code');
});

test('without a token, or with an unverified email, the API answers in JSON', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
    Sanctum::actingAs(User::factory()->unverified()->create());
    $this->getJson('/api/v1/me')->assertForbidden()->assertJsonStructure(['message']);
});

test('a worker reports from the app with photos, a retry with the same client_ref is stored once, and they see only their own reports', function () {
    Storage::fake('local');
    $worker = apiUser('worker');
    makeApiObservation(['reporter_id' => User::factory()->create()->id]);
    $report = ['site_id' => $this->site->id, 'area_id' => $this->area->id, 'type' => 'unsafe-act', 'topic' => 'ppe', 'potential' => 'medium',
        'description' => 'No glasses at the grinder.', 'observed_at' => now()->subMinutes(5)->toIso8601String(), 'client_ref' => (string) Str::uuid()];

    $this->post('/api/v1/observations', [...$report, 'photos' => [UploadedFile::fake()->image('a.jpg')]], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('reporter_id', $worker->id);
    $this->postJson('/api/v1/observations', $report)->assertOk();
    $this->postJson('/api/v1/observations', [...$report, 'client_ref' => (string) Str::uuid(), 'site_id' => $this->other->id])->assertJsonValidationErrors('site_id');

    $mine = Observation::query()->where('reporter_id', $worker->id)->sole();
    expect($mine->photos()->count())->toBe(1);
    $this->getJson('/api/v1/observations')->assertOk()->assertJsonCount(1, 'data');
    $photo = $mine->photos()->sole();
    $this->get("/api/v1/photos/{$photo->id}")->assertOk();
    $this->getJson('/api/v1/options')->assertOk()->assertJsonCount(1, 'sites')->assertJsonPath('checklists', []);
});

function makeApiObservation(array $overrides = []): Observation
{
    return Observation::create(['site_id' => test()->site->id, 'type' => 'near-miss', 'potential' => 'high', 'description' => 'x', 'observed_at' => now(), ...$overrides]);
}

test('an assigned action is notified, done in the app, verified by someone else or sent back with a notice', function () {
    $owner = apiUser('worker');
    $supervisor = test()->userWithRole('supervisor');
    $supervisor->update(['site_id' => $this->site->id]);
    $this->actingAs($supervisor);
    $action = makeApiObservation()->raiseAction(['description' => 'Fit the guardrail.', 'control_level' => 'engineering', 'priority' => 'high', 'owner_id' => $owner->id, 'due_on' => today()]);

    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/notifications')->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.target', ['type' => 'action', 'id' => $action->id]);
    $this->getJson('/api/v1/actions')->assertJsonPath('mine.0.number', $action->number)->assertJsonPath('mine.0.source_ref.type', 'observation');
    $this->postJson("/api/v1/actions/{$action->id}/done", [])->assertJsonValidationErrors('notes');
    $this->postJson("/api/v1/actions/{$action->id}/done", ['notes' => 'Fitted and photographed.'])->assertOk()->assertJsonPath('status', 'done');
    $this->postJson("/api/v1/actions/{$action->id}/verify")->assertForbidden();
    $this->postJson('/api/v1/notifications/read')->assertNoContent();

    Sanctum::actingAs($supervisor);
    $this->travel(1)->minute();
    $this->getJson('/api/v1/actions')->assertJsonPath('to_verify.0.id', $action->id);
    $this->postJson("/api/v1/actions/{$action->id}/reject", ['notes' => 'Toe board missing.'])->assertOk()->assertJsonPath('status', 'open');

    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/notifications')->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.body', 'Toe board missing.');
});

test('an inspector starts, answers and completes an inspection from the app; others cannot fill it in', function () {
    $this->seed(ChecklistSeeder::class);
    $inspector = apiUser('supervisor');
    $template = ChecklistTemplate::query()->where('name', 'Daily site walk')->firstOrFail();

    $id = $this->postJson('/api/v1/inspections', ['checklist_template_id' => $template->id, 'site_id' => $this->site->id])->assertCreated()->json('id');
    $inspection = Inspection::query()->findOrFail($id);
    $answers = $inspection->answers->mapWithKeys(fn ($a) => [$a->id => ['answer' => match ($a->response_type) {
        'yes-no-na' => 'yes', 'rating' => '4', 'number' => '1', default => 'All good',
    }]])->all();
    $first = $inspection->answers->firstWhere('response_type', 'yes-no-na');
    $answers[$first->id] = ['answer' => 'no', 'notes' => 'Edge open.'];

    $this->postJson("/api/v1/inspections/{$id}/answers", ['answers' => [$first->id => ['answer' => 'maybe']]])->assertJsonValidationErrors("answers.{$first->id}.answer");
    Sanctum::actingAs(tap(test()->userWithRole('supervisor'))->update(['site_id' => $this->site->id]));
    $this->postJson("/api/v1/inspections/{$id}/answers", ['answers' => $answers])->assertJsonValidationErrors('status');

    Sanctum::actingAs($inspector);
    $this->postJson("/api/v1/inspections/{$id}/answers", ['answers' => $answers, 'complete' => true])->assertOk()
        ->assertJsonPath('status', 'completed')->assertJsonCount(1, 'actions');
});

test('permit issuers hear of a new request; the app records gas tests and isolations and moves the permit, signed', function () {
    $issuer = test()->userWithRole('permit-issuer');
    $issuer->update(['site_id' => $this->site->id, 'password' => Hash::make('secret-pass')]);
    test()->userWithRole('permit-issuer')->update(['site_id' => $this->other->id]);
    $ra = RiskAssessment::create(['site_id' => $this->site->id, 'type' => 'jsa', 'title' => 'Sump', 'activity' => 'x', 'review_due_on' => today()->addYear()]);
    $ra->hazards()->create(['hazard' => 'Gas', 'likelihood' => 3, 'severity' => 5, 'residual_likelihood' => 1, 'residual_severity' => 5]);
    $ra->approve(User::factory()->create());
    $requester = apiUser('supervisor');
    $this->actingAs($requester);
    $permit = Permit::create(['type' => 'confined-space', 'site_id' => $this->site->id, 'area_id' => $this->area->id, 'risk_assessment_id' => $ra->id,
        'description' => 'Inspect the sump.', 'valid_from' => now(), 'valid_to' => now()->addHours(4),
        'precautions' => array_fill_keys(array_keys(Permit::precautionsFor('confined-space')), true)]);
    $permit->workers()->sync([$requester->id]);

    expect($issuer->notifications()->count())->toBe(1)->and($requester->notifications()->count())->toBe(0);

    Sanctum::actingAs($requester);
    $this->getJson('/api/v1/permits')->assertJsonPath('data.0.number', $permit->number);
    $this->postJson("/api/v1/permits/{$permit->id}/gas-tests", ['oxygen' => 20.9, 'lel' => 0, 'h2s' => 0, 'co' => 2])->assertCreated()->assertJsonPath('passed', true);
    $isolation = $this->postJson("/api/v1/permits/{$permit->id}/isolations", ['point' => 'Sump pump MCC', 'method' => 'Lock and tag'])->assertCreated()->json('id');
    $this->postJson("/api/v1/permits/{$permit->id}/status", ['status' => 'approved', 'password' => 'password'])->assertForbidden();

    Sanctum::actingAs($issuer);
    $this->getJson("/api/v1/permits/{$permit->id}")->assertJsonPath('problems', []);
    $this->postJson("/api/v1/permits/{$permit->id}/status", ['status' => 'approved', 'password' => 'nope'])->assertJsonValidationErrors('password');
    $this->postJson("/api/v1/permits/{$permit->id}/status", ['status' => 'approved', 'password' => 'secret-pass'])->assertOk()->assertJsonPath('status', 'approved');
    $this->postJson("/api/v1/permits/{$permit->id}/isolations/{$isolation}/remove")->assertOk()->assertJsonPath('removed_by', $issuer->id);
});

test('equipment is checked by tag at the person\'s sites, and they see their own tickets', function () {
    apiUser('worker');
    Equipment::forceCreate(['site_id' => $this->site->id, 'asset_tag' => 'CR-01', 'name' => 'Crane', 'type' => 'lifting', 'inspection_interval_months' => 12, 'last_inspected_on' => today()->subMonth()]);
    Equipment::forceCreate(['site_id' => $this->other->id, 'asset_tag' => 'SA-01', 'name' => 'Crane', 'type' => 'lifting', 'inspection_interval_months' => 12]);

    $this->getJson('/api/v1/equipment/CR-01')->assertOk()->assertJsonPath('fit_for_use', true);
    $this->getJson('/api/v1/equipment/SA-01')->assertNotFound();
    $this->getJson('/api/v1/me/competencies')->assertOk()->assertJsonCount(0);
    $this->getJson('/api/v1/permits')->assertForbidden();
});

test('a person who raises an action for themselves is not notified, and nothing reaches other users', function () {
    Notification::fake();
    $user = apiUser('supervisor');
    $this->actingAs($user);
    makeApiObservation()->raiseAction(['description' => 'x', 'control_level' => 'ppe', 'priority' => 'low', 'owner_id' => $user->id, 'due_on' => today()]);

    Notification::assertNothingSent();
    expect(Action::query()->count())->toBe(1);
});

test('the broadcast notice has the same shape as a list item', function () {
    $alert = new HseAlert('Action ACT-1 assigned to you', 'Fit it.', ['type' => 'action', 'id' => 1], 'https://x.example/actions');

    expect($alert->via(new User))->toBe(['database', 'broadcast'])->and($alert->broadcastAs())->toBe('notification')
        ->and($alert->toBroadcast(new User)->data)->toHaveKeys(['title', 'body', 'target', 'url', 'unread', 'created_at']);
});

test('everyone gets their own health record from the app; HSE managers record results and readings', function () {
    Storage::fake('local');
    $worker = apiUser('worker');
    $programme = HealthProgramme::create(['site_id' => $this->site->id, 'name' => 'Noise: breakers', 'hazard' => 'noise', 'exam_interval_months' => 12,
        'monitoring_interval_months' => 60, 'exposure_limit' => 85, 'unit' => 'dB(A)']);
    $programme->members()->create(['user_id' => $worker->id, 'started_on' => today()->subYear()]);

    $this->getJson('/api/v1/me/health')->assertOk()->assertJsonCount(1, 'memberships')->assertJsonCount(0, 'exams');
    $this->getJson('/api/v1/health')->assertForbidden();

    $manager = apiUser('hse-manager');
    $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('0.due_count', 1);
    $this->getJson("/api/v1/health/{$programme->id}")->assertOk()->assertJsonPath('schedule.0.next_type', 'baseline')->assertJsonPath('outcomes.0', 'normal');
    $this->postJson("/api/v1/health/{$programme->id}/exams", ['user_id' => $worker->id, 'type' => 'periodic', 'examined_on' => today()->toDateString(), 'provider' => 'ATC Klang', 'outcome' => 'fit'])
        ->assertJsonValidationErrors('outcome');
    $exam = $this->post("/api/v1/health/{$programme->id}/exams", ['user_id' => $worker->id, 'type' => 'periodic', 'examined_on' => today()->toDateString(), 'provider' => 'ATC Klang',
        'outcome' => 'permanent-sts', 'file' => UploadedFile::fake()->create('audiogram.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('notify_dosh', true)->json('id');
    $this->postJson("/api/v1/health/{$programme->id}/exams/{$exam}/notified", ['dosh_notified_on' => today()->toDateString(), 'dosh_reference' => 'ONRHD/1'])->assertOk()->assertJsonPath('dosh_reference', 'ONRHD/1');
    $this->postJson("/api/v1/health/{$programme->id}/exams/{$exam}/informed", ['informed_on' => today()->toDateString()])->assertOk();
    $this->postJson("/api/v1/health/{$programme->id}/readings", ['measured_on' => today()->toDateString(), 'value' => 91, 'assessor' => 'NRA Lim'])->assertCreated()->assertJsonPath('over_limit', true);

    Sanctum::actingAs($worker);
    $this->getJson('/api/v1/me/health')->assertJsonPath('exams.0.outcome', 'permanent-sts');
    $this->get("/api/v1/me/health/exams/{$exam}/file")->assertOk();
    Sanctum::actingAs(tap($this->userWithRole('worker'))->update(['site_id' => $this->site->id]));
    $this->getJson("/api/v1/me/health/exams/{$exam}/file")->assertNotFound();
    expect($manager->can('manage-health'))->toBeTrue();
});
