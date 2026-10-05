<?php

namespace App\Actions\Incidents;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Models\Incident;
use RuntimeException;

/**
 * Turns a plain-words account of what happened into a draft incident report (type, title,
 * description, immediate actions, area) for a person to check and correct before submitting.
 * Only the typed text and the site's area names are sent; nothing is saved here.
 */
class DraftIncident
{
    private const SYSTEM = <<<'TXT'
        You turn a worker's or supervisor's plain-words account of a workplace incident into a draft incident report.
        Use only facts stated in the account. Never invent times, injuries, causes, equipment or people.
        Replace any person's name with their role (for example "the scaffolder", "a visitor").
        title: a short factual headline (under 80 characters), e.g. "Hand caught in press guard".
        description: what happened, in clear past-tense sentences, in the order it happened.
        immediate_actions: what was done straight away (first aid, isolation, barricading...), or null if the account does not say.
        type: the single best incident type from the allowed list.
        area: the matching name from the list of areas given, or null if none clearly matches.
        Write in the same language as the account.
        TXT;

    public function __construct(private Client $client) {}

    public static function isEnabled(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * @param  list<string>  $areas  the site's area names
     * @return array{type: string, title: string, description: string, immediate_actions: string|null, area: string|null}
     */
    public function draft(string $account, array $areas): array
    {
        $data = $this->ask('Areas at this site: '.($areas ? implode('; ', $areas) : 'none listed').".\n\nAccount:\n".$account);

        $type = in_array($data['type'] ?? null, Incident::TYPES, true) ? $data['type'] : 'injury';
        $area = in_array($data['area'] ?? null, $areas, true) ? $data['area'] : null;

        return [
            'type' => $type,
            'title' => mb_substr(trim((string) ($data['title'] ?? '')), 0, 255),
            'description' => trim((string) ($data['description'] ?? '')),
            'immediate_actions' => filled($data['immediate_actions'] ?? null) ? trim((string) $data['immediate_actions']) : null,
            'area' => $area,
        ];
    }

    /**
     * Send the request and return the decoded JSON. Kept separate so tests can replace it.
     *
     * @return array<string, mixed>
     */
    protected function ask(string $prompt): array
    {
        try {
            $message = $this->client->beta->messages->create(
                model: (string) config('services.anthropic.model'),
                maxTokens: 16000,
                system: self::SYSTEM,
                messages: [['role' => 'user', 'content' => $prompt]],
                // A short, well-specified rewrite: low effort is plenty and keeps it quick.
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::schema()]],
                // On a policy decline, the API retries on a fallback model inside the same call.
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
            );
        } catch (APIException $e) {
            report($e);

            throw new RuntimeException(__('The draft could not be made right now. Fill in the form yourself.'), previous: $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException(__('This account could not be drafted. Fill in the form yourself.'));
        }

        if ($message->stopReason === 'max_tokens') {
            throw new RuntimeException(__('The account is too long to draft. Shorten it or fill in the form yourself.'));
        }

        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                $data = json_decode($block->text, true);

                if (is_array($data)) {
                    return $data;
                }
            }
        }

        throw new RuntimeException(__('The draft came back empty. Fill in the form yourself.'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => Incident::TYPES],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'immediate_actions' => ['type' => ['string', 'null']],
                'area' => ['type' => ['string', 'null']],
            ],
            'required' => ['type', 'title', 'description', 'immediate_actions', 'area'],
            'additionalProperties' => false,
        ];
    }
}
