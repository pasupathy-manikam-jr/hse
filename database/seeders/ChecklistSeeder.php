<?php

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;

/**
 * The starter checklist templates. Idempotent: a template that already exists is left alone.
 */
class ChecklistSeeder extends Seeder
{
    /**
     * name => [description, [[question, type, min, max, critical], ...]]
     *
     * @var array<string, array{string, list<array{string, string, int|float|null, int|float|null, bool}>}>
     */
    private const TEMPLATES = [
        'Daily site walk' => ['A quick walk round at the start of each shift.', [
            ['Are walkways and stairs clear of materials and trip hazards?', 'yes-no-na', null, null, false],
            ['Is edge protection in place at every open edge?', 'yes-no-na', null, null, true],
            ['Are excavations barricaded and signed?', 'yes-no-na', null, null, true],
            ['Is everyone wearing the required PPE?', 'yes-no-na', null, null, false],
            ['Are welfare facilities clean and stocked?', 'yes-no-na', null, null, false],
            ['Anything else to note?', 'text', null, null, false],
        ]],
        'Scaffold inspection' => ['Before first use, weekly, and after bad weather (scaffold tag updated).', [
            ['Is the scaffold tag green and in date?', 'yes-no-na', null, null, true],
            ['Are base plates and sole boards sound and level?', 'yes-no-na', null, null, true],
            ['Are guardrails and toe boards fitted on every working platform?', 'yes-no-na', null, null, true],
            ['Are platforms fully boarded with no gaps?', 'yes-no-na', null, null, false],
            ['Are ties in place at the required spacing?', 'yes-no-na', null, null, true],
            ['Is safe ladder access provided?', 'yes-no-na', null, null, false],
        ]],
        'Fire extinguisher check' => ['Monthly visual check of each extinguisher point.', [
            ['Is the extinguisher in its marked place and unobstructed?', 'yes-no-na', null, null, false],
            ['Pressure gauge reading (bar)', 'number', 12, 16, true],
            ['Is the safety pin and tamper seal intact?', 'yes-no-na', null, null, false],
            ['Is the service label within 12 months?', 'yes-no-na', null, null, false],
        ]],
        'PPE compliance' => ['Spot check of personal protective equipment in a work area.', [
            ['Hard hats worn by everyone in the area?', 'yes-no-na', null, null, false],
            ['Safety footwear worn?', 'yes-no-na', null, null, false],
            ['High-visibility clothing worn?', 'yes-no-na', null, null, false],
            ['Eye and face protection used for grinding or cutting?', 'yes-no-na', null, null, true],
            ['Number of people seen without required PPE', 'number', 0, 0, false],
        ]],
        'Housekeeping' => ['Weekly check of order and tidiness.', [
            ['Is waste removed and segregated?', 'yes-no-na', null, null, false],
            ['Are materials stacked safely and not blocking exits?', 'yes-no-na', null, null, false],
            ['Are spills cleaned up?', 'yes-no-na', null, null, false],
            ['Are fire exits and extinguishers clear?', 'yes-no-na', null, null, true],
        ]],
        'Vehicle pre-use check' => ['Driver check before the first use each shift (forklift, dumper, van).', [
            ['Do brakes work correctly?', 'yes-no-na', null, null, true],
            ['Do lights, horn and reversing alarm work?', 'yes-no-na', null, null, true],
            ['Tyre condition acceptable?', 'yes-no-na', null, null, false],
            ['Any fluid leaks?', 'yes-no-na', null, null, false],
            ['Hour meter reading', 'number', null, null, false],
        ]],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $name => [$description, $items]) {
            $template = ChecklistTemplate::query()->firstOrCreate(['name' => $name], ['description' => $description]);

            if ($template->items()->exists()) {
                continue;
            }

            foreach ($items as $sort => [$question, $type, $min, $max, $critical]) {
                $template->items()->create(['question' => $question, 'response_type' => $type, 'min' => $min, 'max' => $max, 'critical' => $critical, 'sort' => $sort]);
            }
        }
    }
}
