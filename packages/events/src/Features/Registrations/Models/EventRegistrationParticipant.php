<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Models;

use AIArmada\Contacting\Features\ContactMethods\Concerns\HasContactMethods;
use AIArmada\Events\Database\Factories\EventRegistrationParticipantFactory;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Events\Platform\ScopesByEventOwner;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_registration_id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $participant_type
 * @property string|null $participant_id
 * @property string $name
 * @property string|null $relationship_to_registrant
 * @property bool $is_primary
 * @property bool $is_purchaser
 * @property int|null $age
 * @property string|null $gender
 * @property string $status
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $event
 * @property-read EventRegistration $registration
 * @property-read Model|null $occurrence
 * @property-read Model|null $session
 */
final class EventRegistrationParticipant extends Model
{
    use HasContactMethods {
        resolveEmail as resolveContactMethodEmail;
    }
    use HasFactory;
    use HasUuids;
    use ScopesByEventOwner;

    protected $fillable = [
        'event_registration_id',
        'event_id', 'event_occurrence_id', 'event_session_id',
        'participant_type', 'participant_id',
        'name',
        'relationship_to_registrant', 'is_primary', 'is_purchaser',
        'age', 'gender',
        'status', 'notes',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_registration_participants', 'event_registration_participants');
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_purchaser' => 'boolean',
            'age' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function resolveEmail(bool $publicOnly = false): ?string
    {
        if (! $this->relationLoaded('contactMethods')) {
            return $this->resolveContactMethodEmail($publicOnly);
        }

        $now = CarbonImmutable::now();

        /** @var Collection<int, Model> $contactMethods */
        $contactMethods = $this->getRelation('contactMethods');

        $contact = $contactMethods
            ->filter(function (Model $contactMethod) use ($now, $publicOnly): bool {
                if ($contactMethod->getAttribute('type') !== 'email'
                    || $contactMethod->getAttribute('normalized_value') === null) {
                    return false;
                }

                if ($publicOnly && ! (bool) $contactMethod->getAttribute('is_public')) {
                    return false;
                }

                $validFrom = $contactMethod->getAttribute('valid_from');
                $validUntil = $contactMethod->getAttribute('valid_until');

                if ($validFrom !== null && ! $this->isWithinValidityStart($validFrom, $now)) {
                    return false;
                }

                return $validUntil === null || $this->isWithinValidityEnd($validUntil, $now);
            })
            ->sortBy([
                ['is_primary', 'desc'],
                ['sort_order', 'asc'],
            ])
            ->first();

        if (! $contact instanceof Model) {
            return null;
        }

        $value = $contact->getAttribute('normalized_value') ?? $contact->getAttribute('value');

        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }

    private function isWithinValidityStart(mixed $value, CarbonImmutable $now): bool
    {
        if (! $value instanceof DateTimeInterface && ! is_string($value)) {
            return false;
        }

        return ! CarbonImmutable::parse($value)->isAfter($now);
    }

    private function isWithinValidityEnd(mixed $value, CarbonImmutable $now): bool
    {
        if (! $value instanceof DateTimeInterface && ! is_string($value)) {
            return false;
        }

        return ! CarbonImmutable::parse($value)->isBefore($now);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function event(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::eventClass();

        return $this->belongsTo($modelClass, 'event_id');
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    protected static function eventOwnerRelation(): string
    {
        return 'registration.event';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function occurrence(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::occurrenceClass();

        return $this->belongsTo($modelClass, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function session(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::sessionClass();

        return $this->belongsTo($modelClass, 'event_session_id');
    }

    /**
     * @return HasMany<EventRegistrationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(EventRegistrationAnswer::class, 'event_registration_participant_id');
    }

    /**
     * @return BelongsToMany<Pass, $this>
     */
    public function passes(): BelongsToMany
    {
        $passHolderTable = (new PassHolder)->getTable();

        return $this->belongsToMany(
            Pass::class,
            $passHolderTable,
            'holder_id',
            'pass_id',
        )->wherePivot('holder_type', $this->getMorphClass())
            ->wherePivot('is_current', true);
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function attendances(): HasMany
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::attendanceClass();

        return $this->hasMany($modelClass, 'event_registration_participant_id');
    }

    protected static function newFactory(): EventRegistrationParticipantFactory
    {
        return EventRegistrationParticipantFactory::new();
    }
}
