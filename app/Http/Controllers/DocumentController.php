<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\IsoClause;
use App\Models\User;
use App\Support\TableQuery;
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
 * Document control (ISO 45001 §7.5): documents, their revisions, readers and periodic review.
 */
class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Document::query()->with('owner:id,name', 'effectiveRevision:id,document_id,revision')
            ->when(in_array($request->input('type'), Document::TYPES, true), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($request->input('review') === 'due', fn (Builder $q) => $q->dueForReview());

        return Inertia::render('documents/index', [
            'documents' => TableQuery::paginate($query, $request, ['number', 'title'], ['number', 'title', 'next_review_on'], 'number'),
            'people' => User::query()->orderBy('name')->get(['id', 'name']),
            'clauses' => IsoClause::query()->orderBy('id')->get(['id', 'number', 'title']),
            'filters' => TableQuery::filters($request, ['type', 'review']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->documentData($request);
        $document = Document::create(Arr::except($data, 'clause_ids'));
        $document->clauses()->sync($data['clause_ids'] ?? []);
        $document->startRevision();

        return to_route('documents.show', $document);
    }

    public function show(Document $document): Response
    {
        return Inertia::render('documents/show', [
            'document' => $document->load('owner:id,name', 'clauses:id,number,title', 'revisions.approver:id,name', 'revisions.creator:id,name',
                'revisions.readers:id,name', 'revisions.signatures'),
            'people' => User::query()->orderBy('name')->get(['id', 'name']),
            'clauses' => IsoClause::query()->orderBy('id')->get(['id', 'number', 'title']),
            'reviewDue' => $document->next_review_on?->lte(today()->addDays(Document::REVIEW_WINDOW_DAYS)) ?? false,
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $data = $this->documentData($request, $document);
        $document->update(Arr::except($data, 'clause_ids'));
        $document->clauses()->sync($data['clause_ids'] ?? []);

        return $this->done(__('Document updated.'));
    }

    public function startRevision(Document $document): RedirectResponse
    {
        $document->startRevision();

        return $this->done(__('New draft revision started.'));
    }

    public function confirmReview(Document $document): RedirectResponse
    {
        $document->confirmReview();

        return $this->done(__('Reviewed: next review :d.', ['d' => $document->next_review_on?->toDateString()]));
    }

    /**
     * Save a draft's change summary and file (POST: it carries an upload).
     */
    public function saveRevision(Request $request, Document $document, DocumentRevision $revision): RedirectResponse
    {
        $this->belongs($document, $revision);

        if ($revision->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('Only a draft revision can be changed.')]);
        }

        $data = $request->validate([
            'change_summary' => ['nullable', 'string', 'max:2000'],
            'file' => DocumentRevision::uploadRules(),
        ]);
        $revision->fill(['change_summary' => $data['change_summary'] ?? null]);

        if ($request->hasFile('file')) {
            $revision->attachUpload($request->file('file'));
        }

        $revision->save();

        return $this->done(__('Revision saved.'));
    }

    /**
     * submit, return (to draft), or approve (signed).
     */
    public function moveRevision(Request $request, Document $document, DocumentRevision $revision): RedirectResponse
    {
        $this->belongs($document, $revision);
        $data = $request->validate(['to' => ['required', Rule::in(['submit', 'return', 'approve'])], 'password' => ['nullable', 'string']]);
        $user = $this->user($request);

        abort_if(in_array($data['to'], ['return', 'approve'], true) && ! $user->can('approve-documents'), 403);

        match ($request->string('to')->toString()) {
            'submit' => $revision->submit(),
            'return' => $revision->returnToDraft(),
            'approve' => $revision->approve($user, $data['password'] ?? null),
            default => abort(422),
        };

        return $this->done(__('Revision :r updated.', ['r' => $revision->revision]));
    }

    /**
     * Ask people to read the effective revision (they acknowledge from their dashboard).
     */
    public function assignReaders(Request $request, Document $document, DocumentRevision $revision): RedirectResponse
    {
        $this->belongs($document, $revision);
        $ids = $request->validate(['user_ids' => ['required', 'array'], 'user_ids.*' => ['integer', Rule::exists('users', 'id')]])['user_ids'];
        $revision->readers()->syncWithoutDetaching($ids);

        return $this->done(__('Readers assigned.'));
    }

    /**
     * The documents this user has been asked to read: open, then acknowledge.
     */
    public function reading(Request $request): Response
    {
        return Inertia::render('documents/reading', [
            'revisions' => $this->user($request)->readings()->where('status', 'effective')
                ->with('document:id,number,title,type')->orderByPivot('acknowledged_at')->get()
                ->map(fn (DocumentRevision $r) => [
                    'id' => $r->id,
                    'revision' => $r->revision,
                    'change_summary' => $r->change_summary,
                    'document' => $r->document->only(['number', 'title', 'type']),
                    'has_file' => $r->file_path !== null,
                    'acknowledged_at' => $r->getRelationValue('pivot')?->getAttribute('acknowledged_at'),
                ]),
        ]);
    }

    /**
     * Any reader confirms they have read the revision.
     */
    public function acknowledge(Request $request, DocumentRevision $revision): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($revision->readers()->whereKey($user->id)->exists(), 404);
        $revision->readers()->updateExistingPivot($user->id, ['acknowledged_at' => now()]);

        return $this->done(__('Thank you: reading recorded.'));
    }

    /**
     * The revision file, for document managers and for the people asked to read it.
     */
    public function file(Request $request, DocumentRevision $revision): StreamedResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('manage-documents') || $revision->readers()->whereKey($user->id)->exists(), 404);

        return $revision->downloadUpload();
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Request $request, ?Document $document = null): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:50', Rule::unique('documents')->ignore($document)],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Document::TYPES)],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
            'review_interval_months' => ['required', 'integer', 'between:1,60'],
            'clause_ids' => ['nullable', 'array'],
            'clause_ids.*' => ['integer', Rule::exists('iso_clauses', 'id')],
        ]);
    }

    private function belongs(Document $document, DocumentRevision $revision): void
    {
        abort_unless($revision->document_id === $document->id, 404);
    }
}
