<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
});

function newDocument(): Document
{
    test()->post(route('documents.store'), ['number' => 'HSE-PRO-001', 'title' => 'Hot work', 'type' => 'procedure', 'review_interval_months' => 12])
        ->assertSessionHasNoErrors();

    return Document::query()->sole();
}

test('a document starts with draft revision A, which needs a file and a summary before review', function () {
    $this->actingAs($this->userWithRole('hse-manager'));
    $document = newDocument();
    $revision = $document->revisions()->sole();

    expect($revision)->revision->toBe('A')->status->toBe('draft');

    $this->put(route('documents.revisions.move', [$document, $revision]), ['to' => 'submit'])->assertSessionHasErrors('status');
    $this->post(route('documents.revisions.save', [$document, $revision]), [
        'change_summary' => 'First issue.', 'file' => UploadedFile::fake()->create('hot-work.pdf', 40, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $this->put(route('documents.revisions.move', [$document, $revision]), ['to' => 'submit'])->assertSessionHasNoErrors();

    expect($revision->fresh())->status->toBe('in-review')->file_sha256->toHaveLength(64);
});

test('approval is signed by someone other than the author and supersedes the previous revision', function () {
    $this->actingAs($author = $this->userWithRole('hse-manager'));
    $document = newDocument();
    $a = $document->revisions()->sole();
    $a->attachUpload(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->fill(['change_summary' => 'x'])->save();
    $a->submit();
    $approver = User::factory()->create(['password' => Hash::make('approve-pass')])->assignRole('hse-manager');

    $this->put(route('documents.revisions.move', [$document, $a]), ['to' => 'approve', 'password' => 'password'])->assertSessionHasErrors('status');
    $this->actingAs($approver)->put(route('documents.revisions.move', [$document, $a]), ['to' => 'approve', 'password' => 'wrong'])->assertSessionHasErrors('password');
    $this->put(route('documents.revisions.move', [$document, $a]), ['to' => 'approve', 'password' => 'approve-pass'])->assertSessionHasNoErrors();

    expect($a->fresh())->status->toBe('effective')->approved_by->toBe($approver->id)
        ->and($a->signatures()->sole()->meaning)->toBe('approved')
        ->and($document->fresh()->next_review_on->toDateString())->toBe(today()->addMonths(12)->toDateString());

    $this->actingAs($author)->post(route('documents.revisions.store', $document))->assertSessionHasNoErrors();
    $b = $document->revisions()->where('revision', 'B')->sole();
    $b->attachUpload(UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'))->fill(['change_summary' => 'y'])->save();
    $b->submit();
    $b->approve($approver, 'approve-pass');

    expect($a->fresh()->status)->toBe('superseded')->and($b->fresh()->status)->toBe('effective');
    $this->post(route('documents.revisions.store', $document));
    $this->post(route('documents.revisions.store', $document))->assertSessionHasErrors('revision');
});

test('readers open and acknowledge the effective revision from their reading list, whatever their role', function () {
    $this->actingAs($this->userWithRole('hse-manager'));
    $document = newDocument();
    $revision = $document->revisions()->sole();
    $revision->attachUpload(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->save();
    $revision->forceFill(['status' => 'effective'])->save();
    $worker = $this->userWithRole('worker');
    $stranger = User::factory()->create();

    $this->put(route('documents.revisions.readers', [$document, $revision]), ['user_ids' => [$worker->id]])->assertSessionHasNoErrors();

    $this->actingAs($worker)->get(route('documents.reading'))
        ->assertInertia(fn (Assert $page) => $page->component('documents/reading')->has('revisions', 1)->where('revisions.0.acknowledged_at', null));
    $this->get(route('documents.revisions.file', $revision))->assertOk();
    $this->put(route('documents.revisions.acknowledge', $revision))->assertSessionHasNoErrors();
    $this->get(route('documents.index'))->assertForbidden();

    expect($worker->readings()->sole()->getRelationValue('pivot')->acknowledged_at)->not->toBeNull();
    $this->actingAs($stranger)->get(route('documents.revisions.file', $revision))->assertNotFound();
});

test('a periodic review with no change moves the next review on', function () {
    $this->actingAs($this->userWithRole('hse-manager'));
    $document = newDocument();

    $this->put(route('documents.review', $document))->assertSessionHasErrors('review');

    $document->revisions()->sole()->forceFill(['status' => 'effective'])->save();
    $document->forceFill(['next_review_on' => today()->addDays(5)])->save();
    $this->put(route('documents.review', $document))->assertSessionHasNoErrors();

    expect($document->fresh()->next_review_on->toDateString())->toBe(today()->addMonths(12)->toDateString());
});
