<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPhotos;
use App\Models\Concerns\RaisesActions;
use App\Models\Concerns\VisibleBySite;
use App\Models\Contracts\ActionSource;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * An observation from the field: unsafe act or condition, near miss, good practice or
 * environmental issue. Open → actioned (an action was raised) → closed, or dismissed with a
 * reason when it is not a valid report. A reporter's good observation can be chosen as the
 * site's observation of the month.
 *
 * @property int $id
 * @property string $number
 * @property string|null $client_ref
 * @property int $site_id
 * @property int|null $area_id
 * @property string $type
 * @property string|null $topic
 * @property int|null $contractor_id
 * @property string $potential
 * @property string $description
 * @property string|null $immediate_action
 * @property CarbonImmutable $observed_at
 * @property bool $anonymous
 * @property int|null $reporter_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $status
 * @property string|null $status_reason
 * @property CarbonImmutable|null $recognised_for
 * @property string|null $recognition_note
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Observation extends Model implements ActionSource
{
    use Auditable, HasPhotos, RaisesActions, VisibleBySite;

    public const TYPES = ['unsafe-act', 'unsafe-condition', 'near-miss', 'positive', 'environmental'];

    public const POTENTIALS = ['low', 'medium', 'high'];

    /** What the observation is about, for trends ("most reports are about PPE"). */
    public const TOPICS = [
        'ppe', 'housekeeping', 'work-at-height', 'lifting', 'mobile-plant', 'electrical', 'fire',
        'hazardous-substances', 'manual-handling', 'tools', 'access', 'behaviour', 'environment', 'other',
    ];

    public const STATUSES = ['open', 'actioned', 'closed', 'dismissed'];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'open' => ['actioned', 'closed', 'dismissed'],
        'actioned' => ['closed'],
    ];

    protected $guarded = ['id', 'number', 'status', 'status_reason', 'closed_by', 'closed_at', 'recognised_for', 'recognition_note'];

    protected static function booted(): void
    {
        static::creating(function (self $observation) {
            $observation->number = Sequence::next('OBS');
            $observation->status = 'open';
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'closed_at' => 'datetime',
            'recognised_for' => 'date:Y-m-d',
            'anonymous' => 'boolean',
        ];
    }

    public function canBeViewedBy(User $user): bool
    {
        // The reporter can always open their own (named) report, its photos included.
        return ($user->can('manage-observations') && $this->isVisibleTo($user)) || ($this->reporter_id !== null && $this->reporter_id === $user->id);
    }

    /**
     * An anonymous report must not record who sent it, in the audit trail either.
     */
    protected function hidesAuditActor(string $event): bool
    {
        return $event === 'created' && $this->anonymous;
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Validation rules for a report (phone form and API): the site must be one the reporter
     * may work on, and the area must be at that site.
     *
     * @return array<string, mixed>
     */
    public static function reportRules(User $user, int $siteId): array
    {
        return [
            'client_ref' => ['nullable', 'uuid'],
            'site_id' => ['required', Rule::exists('sites', 'id')->where('active', true)->when($user->site_id !== null, fn ($rule) => $rule->where('id', $user->site_id))],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('site_id', $siteId)],
            'type' => ['required', Rule::in(Observation::TYPES)],
            'topic' => ['nullable', Rule::in(Observation::TOPICS)],
            'potential' => ['required', Rule::in(Observation::POTENTIALS)],
            'description' => ['required', 'string', 'max:5000'],
            'immediate_action' => ['nullable', 'string', 'max:5000'],
            'observed_at' => ['required', 'date', 'before_or_equal:now'],
            'anonymous' => ['boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            ...Observation::photoRules(),
        ];
    }

    /**
     * Store a validated report and its photos. An anonymous report records no reporter.
     *
     * @param  array<string, mixed>  $data  validated by reportRules()
     * @param  array<int, UploadedFile>  $photos
     */
    public static function report(User $by, array $data, array $photos = []): self
    {
        $anonymous = (bool) ($data['anonymous'] ?? false);
        $observation = self::create([...Arr::except($data, 'photos'), 'anonymous' => $anonymous, 'reporter_id' => $anonymous ? null : $by->id]);
        $observation->attachPhotos($photos);

        return $observation;
    }

    /**
     * Valid reports: everything except dismissed ones. Use for counts and trends.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->where('status', '!=', 'dismissed');
    }

    /**
     * The crew (contractor) involved.
     *
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    /**
     * The incident this observation was escalated to, if any.
     *
     * @return HasOne<Incident, $this>
     */
    public function incident(): HasOne
    {
        return $this->hasOne(Incident::class);
    }

    public function url(): string
    {
        return route('observations.show', $this);
    }

    protected function actionRaised(): void
    {
        if ($this->status === 'open') {
            $this->transitionTo('actioned');
        }
    }

    /**
     * Choose this as the site's observation of the month it was made in. One per site and month;
     * a named reporter only, and not a dismissed report.
     */
    public function recognise(string $note): void
    {
        if ($this->reporter_id === null || $this->status === 'dismissed') {
            throw ValidationException::withMessages(['status' => __('Only a valid report from a named person can be recognised.')]);
        }

        $month = $this->observed_at->startOfMonth()->toDateString();
        $taken = self::query()->where('site_id', $this->site_id)->whereDate('recognised_for', $month)->whereKeyNot($this->id)->value('number');

        if ($taken !== null) {
            throw ValidationException::withMessages(['status' => __(':number is already the observation of the month.', ['number' => $taken])]);
        }

        $this->forceFill(['recognised_for' => $month, 'recognition_note' => $note])->save();
    }

    public function transitionTo(string $status, ?string $reason = null): void
    {
        if (! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('An :from observation cannot be marked :to.', ['from' => $this->status, 'to' => $status])]);
        }

        if ($status === 'dismissed' && (blank($reason) || $this->actions()->exists())) {
            throw ValidationException::withMessages(['status' => __('Dismiss only a report with no actions, and say why.')]);
        }

        if ($status === 'closed' && $this->hasUnverifiedActions()) {
            throw ValidationException::withMessages(['status' => __('Every action must be verified before the observation is closed.')]);
        }

        $this->forceFill(match ($status) {
            'closed' => ['closed_by' => auth()->id(), 'closed_at' => now()],
            'dismissed' => ['closed_by' => auth()->id(), 'closed_at' => now(), 'status_reason' => $reason, 'recognised_for' => null, 'recognition_note' => null],
            default => [],
        })->forceFill(['status' => $status])->save();
    }
}
