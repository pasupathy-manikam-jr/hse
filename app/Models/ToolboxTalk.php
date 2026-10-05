<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\VisibleBySite;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A short safety briefing at a site, with who attended. It can share the lessons of an incident.
 *
 * @property int $id
 * @property string $number
 * @property int $site_id
 * @property string $topic
 * @property string|null $notes
 * @property CarbonImmutable $held_on
 * @property int|null $presenter_id
 * @property int|null $incident_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ToolboxTalk extends Model
{
    use Auditable, HasCreator, VisibleBySite;

    protected $guarded = ['id', 'number', 'created_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $talk) => $talk->number = Sequence::next('TBT'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'held_on' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'toolbox_talk_attendees')->withTrashed()->orderBy('name');
    }
}
