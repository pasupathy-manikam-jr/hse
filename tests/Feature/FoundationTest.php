<?php

use App\Models\AuditLog;
use App\Models\Site;
use App\Support\Sequence;
use Inertia\Testing\AssertableInertia;

test('sequence numbers count up per prefix and year', function () {
    $year = now()->year;

    expect(Sequence::next('INC'))->toBe("INC-{$year}-0001")
        ->and(Sequence::next('INC'))->toBe("INC-{$year}-0002")
        ->and(Sequence::next('OBS'))->toBe("OBS-{$year}-0001");
});

test('changes are written to the audit trail, which cannot be edited or deleted', function () {
    $this->actingAs($user = $this->userWithRole());
    $site = Site::create(['code' => 'A1', 'name' => 'Alpha']);
    $site->update(['name' => 'Alpha Plant']);

    $log = $site->auditLogs()->latest('id')->firstOrFail();

    expect($site->auditLogs()->pluck('event')->all())->toBe(['created', 'updated'])
        ->and($log->user_id)->toBe($user->id)
        ->and($log->old_values)->toBe(['name' => 'Alpha'])
        ->and($log->new_values)->toBe(['name' => 'Alpha Plant'])
        ->and(fn () => $log->update(['event' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

test('hidden user attributes never reach the audit trail', function () {
    $user = $this->userWithRole();

    expect(AuditLog::query()->where('auditable_id', $user->id)->where('event', 'created')->value('new_values'))
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

test('a forbidden page renders the error page with its status', function () {
    $this->actingAs($this->userWithRole('worker'))
        ->get(route('sites.index'))
        ->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('error')->where('status', 403));
});

test('a missing page renders the error page for guests too', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('error')->where('status', 404));
});
