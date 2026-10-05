<?php

use Anthropic\Client;
use App\Actions\Incidents\DraftIncident;
use App\Models\Site;

beforeEach(function () {
    $this->site = Site::create(['code' => 'KL', 'name' => 'Tower']);
    $this->site->areas()->create(['name' => 'Block A']);
});

/**
 * Replace the Claude call with a canned reply (or a failure).
 *
 * @param  array<string, mixed>|null  $reply
 */
function fakeDrafter(?array $reply): void
{
    config(['services.anthropic.key' => 'test-key']);

    app()->bind(DraftIncident::class, fn () => new class(app(Client::class), $reply) extends DraftIncident
    {
        /** @param array<string, mixed>|null $reply */
        public function __construct(Client $client, private ?array $reply)
        {
            parent::__construct($client);
        }

        protected function ask(string $prompt): array
        {
            return $this->reply ?? throw new RuntimeException('The draft could not be made right now. Fill in the form yourself.');
        }
    });
}

test('drafting is off without an API key', function () {
    config(['services.anthropic.key' => null]);
    $this->actingAs($this->userWithRole('supervisor'));

    $this->get(route('incidents.create'))->assertInertia(fn ($page) => $page->where('aiDrafting', false));
    $this->postJson(route('incidents.draft'), ['account' => str_repeat('x', 30)])->assertNotFound();
});

test('a plain account becomes a draft, keeping only known types and areas', function () {
    fakeDrafter([
        'type' => 'injury', 'title' => 'Hand caught between scaffold tubes', 'description' => 'The scaffolder\'s hand was caught.',
        'immediate_actions' => 'First aid given.', 'area' => 'Block A',
    ]);
    $this->actingAs($this->userWithRole('supervisor'));

    $this->postJson(route('incidents.draft'), ['account' => 'Ali got his hand caught passing tubes on level 6 of block A, first aid given.', 'site_id' => $this->site->id])
        ->assertOk()
        ->assertJsonPath('draft.title', 'Hand caught between scaffold tubes')
        ->assertJsonPath('draft.area', 'Block A');

    fakeDrafter(['type' => 'alien-abduction', 'title' => 'x', 'description' => 'x', 'immediate_actions' => null, 'area' => 'Mars']);
    $this->postJson(route('incidents.draft'), ['account' => str_repeat('Something happened. ', 3), 'site_id' => $this->site->id])
        ->assertJsonPath('draft.type', 'injury')
        ->assertJsonPath('draft.area', null);
});

test('the account is validated, and a failed draft is reported on the field', function () {
    fakeDrafter(null);
    $this->actingAs($this->userWithRole('supervisor'));

    $this->postJson(route('incidents.draft'), ['account' => 'too short'])->assertJsonValidationErrors('account');
    $this->postJson(route('incidents.draft'), ['account' => str_repeat('Something happened. ', 3)])
        ->assertStatus(422)
        ->assertJsonPath('errors.account.0', 'The draft could not be made right now. Fill in the form yourself.');
});

test('only people who report incidents can draft them', function () {
    fakeDrafter(['type' => 'injury', 'title' => 'x', 'description' => 'x', 'immediate_actions' => null, 'area' => null]);

    $this->actingAs($this->userWithRole('worker'))->postJson(route('incidents.draft'), ['account' => str_repeat('x', 30)])->assertForbidden();
});
