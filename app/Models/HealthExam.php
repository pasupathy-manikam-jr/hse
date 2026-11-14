<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\StoresUploads;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * One examination of a worker under a health programme (an audiogram, or medical surveillance by
 * an occupational health doctor), with its report. Never deleted: kept 30 years (USECHH reg. 27(4)).
 *
 * @property int $id
 * @property int $health_programme_id
 * @property int $user_id
 * @property string $type
 * @property CarbonImmutable $examined_on
 * @property string $provider
 * @property string $outcome
 * @property string|null $notes
 * @property CarbonImmutable|null $informed_on
 * @property CarbonImmutable|null $dosh_notified_on
 * @property string|null $dosh_reference
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $file_type
 * @property int|null $file_size
 * @property string|null $file_sha256
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class HealthExam extends Model
{
    use Auditable, HasCreator, StoresUploads;

    public const UPLOAD_DIRECTORY = 'health';

    public const TYPES = ['baseline', 'periodic', 'retest', 'exit'];

    /**
     * Outcomes by hazard. Noise, from the DOSH code of practice: a temporary or permanent standard
     * threshold shift (10 dB or more at 2, 3 and 4 kHz against the baseline), hearing impairment,
     * noise-induced hearing loss. Chemical: the occupational health doctor's finding; "unfit" means
     * removed from the exposure (USECHH reg. 28).
     *
     * @var array<string, list<string>>
     */
    public const OUTCOMES = [
        'noise' => ['normal', 'temporary-sts', 'permanent-sts', 'hearing-impairment', 'nihl'],
        'chemical' => ['fit', 'fit-with-restrictions', 'unfit', 'occupational-disease'],
    ];

    /** @var array<string, string> */
    public const OUTCOME_LABELS = [
        'normal' => 'normal', 'temporary-sts' => 'temporary threshold shift', 'permanent-sts' => 'permanent threshold shift',
        'hearing-impairment' => 'hearing impairment', 'nihl' => 'noise-induced hearing loss', 'fit' => 'fit',
        'fit-with-restrictions' => 'fit with restrictions', 'unfit' => 'unfit', 'occupational-disease' => 'occupational disease',
    ];

    /** Outcomes the employer must notify to the nearest DOSH office within 7 days. */
    public const NOTIFIABLE = ['permanent-sts', 'hearing-impairment', 'nihl', 'occupational-disease'];

    public const NOTIFY_DAYS = 7;

    /** The worker is told their result within this many days of the employer receiving it (noise code 13.3.5). */
    public const INFORM_DAYS = 21;

    protected $guarded = ['id', 'created_by', 'informed_on', 'dosh_notified_on', 'dosh_reference', 'file_path', 'file_name', 'file_type', 'file_size', 'file_sha256'];

    /** @var list<string> */
    protected $hidden = ['file_path'];

    /** @var list<string> */
    protected $appends = ['notify_dosh'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examined_on' => 'date:Y-m-d',
            'informed_on' => 'date:Y-m-d',
            'dosh_notified_on' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<HealthProgramme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(HealthProgramme::class, 'health_programme_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Whether DOSH must be told of this result. Not for hearing loss already present at a baseline
     * taken within 3 months of starting noisy work (noise code 13.5.4).
     *
     * @return Attribute<bool, never>
     */
    protected function notifyDosh(): Attribute
    {
        return Attribute::get(function (): bool {
            if (! in_array($this->outcome, self::NOTIFIABLE, true)) {
                return false;
            }

            if ($this->type !== 'baseline') {
                return true;
            }

            $startedOn = HealthProgrammeMember::query()->where('health_programme_id', $this->health_programme_id)
                ->where('user_id', $this->user_id)->value('started_on');

            return $startedOn === null || $this->examined_on->gt(CarbonImmutable::parse($startedOn)->addMonthsNoOverflow(HealthProgramme::BASELINE_MONTHS));
        });
    }

    /**
     * Record that the worker was told their result.
     */
    public function markInformed(string $on): void
    {
        $this->forceFill(['informed_on' => $on])->save();
    }

    /**
     * Record the notification of a notifiable result to DOSH.
     */
    public function markDoshNotified(string $on, string $reference): void
    {
        if (! $this->notify_dosh) {
            throw ValidationException::withMessages(['status' => __('This result is not notifiable.')]);
        }

        $this->forceFill(['dosh_notified_on' => $on, 'dosh_reference' => $reference])->save();
    }

    /**
     * @return array<string, list<string>>
     */
    public function informedRules(): array
    {
        return ['informed_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$this->examined_on->toDateString()]];
    }

    /**
     * @return array<string, list<string>>
     */
    public function notifiedRules(): array
    {
        return [
            'dosh_notified_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$this->examined_on->toDateString()],
            'dosh_reference' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * The day the result was entered: the employer has it from then.
     */
    public function receivedOn(): CarbonImmutable
    {
        return ($this->created_at ?? CarbonImmutable::now())->startOfDay();
    }
}
