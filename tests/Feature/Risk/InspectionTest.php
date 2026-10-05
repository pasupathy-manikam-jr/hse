<?php

use App\Models\ChecklistTemplate;
use App\Models\Inspection;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->template = ChecklistTemplate::create(['name' => 'Site walk']);
    foreach ([
        ['Walkways clear?', 'yes-no-na', null, null, false],
        ['Edge protection in place?', 'yes-no-na', null, null, true],
        ['Extinguisher pressure (bar)', 'number', 12, 16, false],
        ['Notes', 'text', null, null, false],
    ] as $sort => [$question, $type, $min, $max, $critical]) {
        $this->template->items()->create(['question' => $question, 'response_type' => $type, 'min' => $min, 'max' => $max, 'critical' => $critical, 'sort' => $sort]);
    }
});

function startInspection(): Inspection
{
    test()->post(route('inspections.store'), ['checklist_template_id' => test()->template->id, 'site_id' => test()->site->id])->assertRedirect();

    return Inspection::query()->latest('id')->firstOrFail();
}

/**
 * @param  list<string|null>  $values  one per question, in order
 */
function answersFor(Inspection $inspection, array $values): array
{
    return ['answers' => $inspection->answers->values()->mapWithKeys(fn ($answer, $n) => [$answer->id => ['answer' => $values[$n]]])->all()];
}

test('starting an inspection copies the questions, so template edits never change it', function () {
    $this->actingAs($this->userWithRole('supervisor'));
    $inspection = startInspection();

    $this->template->items()->first()->update(['question' => 'Changed later']);

    expect($inspection)->number->toBe('INS-'.now()->year.'-0001')->status->toBe('in-progress')->template_name->toBe('Site walk')
        ->and($inspection->answers->pluck('question')->all())->toBe(['Walkways clear?', 'Edge protection in place?', 'Extinguisher pressure (bar)', 'Notes']);
});

test('failed answers are saved as failures, with notes and photos', function () {
    Storage::fake('local');
    $this->actingAs($this->userWithRole('supervisor'));
    $inspection = startInspection();
    $edge = $inspection->answers[1];

    $this->post(route('inspections.answers', $inspection), [
        'answers' => [$edge->id => ['answer' => 'no', 'notes' => 'Guardrail missing.', 'photos' => [UploadedFile::fake()->image('edge.jpg')]]],
    ])->assertSessionHasNoErrors();

    expect($edge->fresh())->answer->toBe('no')->passed->toBeFalse()->notes->toBe('Guardrail missing.')
        ->and($edge->photos)->toHaveCount(1);
});

test('completing scores the inspection and raises an action per failure, high priority for critical ones', function () {
    $this->actingAs($inspector = $this->userWithRole('supervisor'));
    $inspection = startInspection();

    $this->post(route('inspections.answers', $inspection), [...answersFor($inspection, ['yes', 'no', '17', 'All fine otherwise.']), 'complete' => true])
        ->assertSessionHasNoErrors();

    $inspection->refresh();
    $actions = $inspection->actions()->get();
    expect($inspection)->status->toBe('completed')->score->toBe(33)->completed_at->not->toBeNull()
        ->and($actions)->toHaveCount(2)
        ->and($actions->pluck('priority')->all())->toBe(['high', 'medium'])
        ->and($actions->first()->owner_id)->toBe($inspector->id)
        ->and($actions->first()->due_on->toDateString())->toBe(today()->addDay()->toDateString());
});

test('N/A and text answers neither pass nor fail', function () {
    $this->actingAs($this->userWithRole('supervisor'));
    $inspection = startInspection();

    $this->post(route('inspections.answers', $inspection), [...answersFor($inspection, ['yes', 'na', '14', 'x']), 'complete' => true]);

    expect($inspection->fresh()->score)->toBe(100)->and($inspection->actions()->count())->toBe(0);
});

test('an inspection cannot complete with unanswered questions, and answers are validated', function () {
    $this->actingAs($this->userWithRole('supervisor'));
    $inspection = startInspection();

    $this->post(route('inspections.answers', $inspection), [...answersFor($inspection, ['maybe', null, null, null])])
        ->assertSessionHasErrors('answers.'.$inspection->answers[0]->id.'.answer');
    $this->post(route('inspections.answers', $inspection), [...answersFor($inspection, ['yes', null, null, null]), 'complete' => true])
        ->assertSessionHasErrors('status');

    expect($inspection->fresh()->status)->toBe('in-progress');
});

test('only the inspector fills it in, and a completed one is final', function () {
    $this->actingAs($this->userWithRole('supervisor'));
    $inspection = startInspection();

    $this->actingAs($this->userWithRole('hse-manager'))->post(route('inspections.answers', $inspection), answersFor($inspection, ['yes', 'yes', '14', null]))
        ->assertSessionHasErrors('status');

    $inspection->complete($inspection->creator);
})->throws(ValidationException::class);

test('a completed inspection cannot be discarded; one in progress can', function () {
    $this->actingAs($inspector = $this->userWithRole('hse-manager'));
    $done = startInspection();
    $this->post(route('inspections.answers', $done), [...answersFor($done, ['yes', 'yes', '14', null]), 'complete' => true]);
    $open = startInspection();

    $this->delete(route('inspections.destroy', $done));
    $this->delete(route('inspections.destroy', $open));

    expect($done->fresh())->not->toBeNull()->and($open->fresh())->toBeNull();
});

test('the list is scoped by site and only active templates with questions can be used', function () {
    ChecklistTemplate::create(['name' => 'Empty']);
    ChecklistTemplate::create(['name' => 'Retired', 'active' => false])->items()->create(['question' => 'q', 'response_type' => 'yes-no-na']);
    $supervisor = $this->userWithRole('supervisor');
    $supervisor->update(['site_id' => $this->site->id]);
    $other = Site::create(['code' => 'SA', 'name' => 'Plant']);

    $this->actingAs($supervisor)->get(route('inspections.index'))
        ->assertInertia(fn (Assert $page) => $page->component('inspections/index')->has('templates', 1)->where('templates.0.name', 'Site walk'));
    $this->post(route('inspections.store'), ['checklist_template_id' => $this->template->id, 'site_id' => $other->id])->assertSessionHasErrors('site_id');

    $this->actingAs($this->userWithRole('worker'))->get(route('inspections.index'))->assertForbidden();
});

test('checklist questions: number limits only for numbers, max not below min', function () {
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->post(route('checklists.items.store', $this->template), ['question' => 'q', 'response_type' => 'yes-no-na', 'min' => 1])->assertSessionHasErrors('min');
    $this->post(route('checklists.items.store', $this->template), ['question' => 'q', 'response_type' => 'number', 'min' => 10, 'max' => 5])->assertSessionHasErrors('max');
    $this->post(route('checklists.items.store', $this->template), ['question' => 'Noise (dB)', 'response_type' => 'number', 'max' => 85, 'critical' => true])->assertSessionHasNoErrors();

    expect($this->template->items()->where('question', 'Noise (dB)')->sole())->critical->toBeTrue()->sort->toBe(4)->max->toBe('85.00');

    $this->actingAs($this->userWithRole('supervisor'))->post(route('checklists.items.store', $this->template), ['question' => 'x', 'response_type' => 'text'])->assertForbidden();
});

test('a rating passes at its minimum (3 by default) and fails below it', function () {
    $this->template->items()->create(['question' => 'Housekeeping (1–5)', 'response_type' => 'rating', 'sort' => 9]);
    $this->template->items()->create(['question' => 'Lighting (1–5)', 'response_type' => 'rating', 'min' => 4, 'sort' => 10]);
    $this->actingAs($this->userWithRole('supervisor'));
    $inspection = startInspection();

    $this->post(route('inspections.answers', $inspection), answersFor($inspection, ['yes', 'yes', '14', null, '6', '3']))
        ->assertSessionHasErrors('answers.'.$inspection->answers[4]->id.'.answer');
    $this->post(route('inspections.answers', $inspection), [...answersFor($inspection, ['yes', 'yes', '14', null, '3', '3']), 'complete' => true])
        ->assertSessionHasNoErrors();

    expect($inspection->answers()->where('response_type', 'rating')->pluck('passed')->all())->toBe([true, false])
        ->and($inspection->fresh()->score)->toBe(80)
        ->and($inspection->actions()->sole()->description)->toContain('Lighting');
});

test('a rating question takes a minimum between 1 and 5 and no maximum', function () {
    $this->actingAs($this->userWithRole('hse-manager'));

    $this->post(route('checklists.items.store', $this->template), ['question' => 'q', 'response_type' => 'rating', 'min' => 7, 'max' => 9])
        ->assertSessionHasErrors(['min', 'max']);
});
