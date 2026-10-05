<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone involved in an incident: injured, a witness, or otherwise involved. Staff link to
 * their user; visitors and others are a name only. Changes re-derive the incident's classification.
 *
 * @property int $id
 * @property int $incident_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $job_title
 * @property string $role
 * @property string|null $treatment
 * @property string|null $illness_type
 * @property bool $privacy_case
 * @property string|null $body_part
 * @property string|null $injury_nature
 * @property int $days_lost
 * @property int $days_restricted
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class IncidentPerson extends Model
{
    use Auditable;

    public const ROLES = ['injured', 'witness', 'involved'];

    /** Least to most severe (Incident::CLASSIFICATIONS without "no-injury"). */
    public const TREATMENTS = ['first-aid', 'medical', 'restricted', 'lost-time', 'fatality'];

    /** OSHA 300 column M for illnesses (an injury is the default). */
    public const ILLNESS_TYPES = ['skin-disorder', 'respiratory', 'poisoning', 'hearing-loss', 'other-illness'];

    protected $table = 'incident_people';

    protected $guarded = ['id', 'incident_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'privacy_case' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $person) => $person->incident->reclassify());
        static::deleted(fn (self $person) => $person->incident->reclassify());
    }

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
