<?php

namespace Database\Seeders;

use App\Models\IsoClause;
use Illuminate\Database\Seeder;

/**
 * ISO 45001:2018 clauses 4–10 (the auditable requirements). Idempotent.
 */
class IsoClauseSeeder extends Seeder
{
    private const CLAUSES = [
        '4.1' => 'Understanding the organization and its context',
        '4.2' => 'Needs and expectations of workers and other interested parties',
        '4.3' => 'Scope of the OH&S management system',
        '4.4' => 'OH&S management system',
        '5.1' => 'Leadership and commitment',
        '5.2' => 'OH&S policy',
        '5.3' => 'Roles, responsibilities and authorities',
        '5.4' => 'Consultation and participation of workers',
        '6.1.1' => 'Actions to address risks and opportunities: general',
        '6.1.2' => 'Hazard identification and assessment of risks and opportunities',
        '6.1.3' => 'Determination of legal and other requirements',
        '6.1.4' => 'Planning action',
        '6.2' => 'OH&S objectives and planning to achieve them',
        '7.1' => 'Resources',
        '7.2' => 'Competence',
        '7.3' => 'Awareness',
        '7.4' => 'Communication',
        '7.5' => 'Documented information',
        '8.1.1' => 'Operational planning and control: general',
        '8.1.2' => 'Eliminating hazards and reducing OH&S risks',
        '8.1.3' => 'Management of change',
        '8.1.4' => 'Procurement (contractors, outsourcing)',
        '8.2' => 'Emergency preparedness and response',
        '9.1' => 'Monitoring, measurement, analysis and performance evaluation',
        '9.2' => 'Internal audit',
        '9.3' => 'Management review',
        '10.1' => 'Improvement: general',
        '10.2' => 'Incident, nonconformity and corrective action',
        '10.3' => 'Continual improvement',
    ];

    public function run(): void
    {
        IsoClause::query()->upsert(
            collect(self::CLAUSES)->map(fn (string $title, string $number) => ['number' => $number, 'title' => $title])->values()->all(),
            ['number'], ['title'],
        );
    }
}
