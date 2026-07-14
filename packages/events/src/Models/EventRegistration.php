<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventRegistrationFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use AIArmada\Events\States\RegistrationStatus\Completed;
use AIArmada\Events\States\RegistrationStatus\Pending;
use AIArmada\Events\States\RegistrationStatus\RegistrationStatus as RegistrationStatusState;
use AIArmada\Events\States\RegistrationStatus\Waitlisted;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Ticketing\Models\Pass;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $registrant_type
 * @property string|null $registrant_id
 * @property string $registration_no
 * @property string $registration_type
 * @property RegistrationStatusState $status
 * @property string $source
 * @property int $total_participants
 * @property int $total_amount
 * @property string $currency
 * @property string|null $external_order_id
 * @property string|null $external_order_type
 * @property string|null $payment_status
 * @property CarbonImmutable|null $registered_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $waitlisted_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable|null $expired_at
 * @property string|null $status_reason
 * @property string|null $notes
 * @property string|null $parent_registration_id
 * @property bool $is_bundle_root
 * @property array<string, mixed>|null $pass_entitlements
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 * @property-read EventRegistration|null $parentRegistration
 * @property-read Collection<int, EventRegistration> $childRegistrations
 * @property-read Model|Eloquent $registrant
 * @property-read Collection<int, EventRegistrationParticipant> $participants
 * @property-read Collection<int, EventRegistrationAnswer> $answers
 * @property-read Collection<int, EventRegistrationItem> $items
 * @property-read Collection<int, Pass> $passes
 * @property-read Collection<int, EventAttendance> $attendances
 */
class EventRegistration extends Model
{
    /** @use HasFactory<EventRegistrationFactory> */
    use HasFactory;

    use HasStates;
    use Notifiable;
    use UsesEventUuid;

    public const array CAPACITY_BLOCKING_STATUSES = [
        'pending',
        'confirmed',
        'checked_in',
        'no_show',
    ];

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'registrant_type', 'registrant_id',
        'registration_no', 'registration_type', 'status', 'source',
        'total_participants', 'total_amount', 'currency',
        'external_order_id', 'external_order_type', 'payment_status',
        'registered_at', 'approved_at', 'completed_at', 'cancelled_at', 'rejected_at',
        'waitlisted_at', 'refunded_at', 'expired_at',
        'status_reason', 'notes',
        'parent_registration_id', 'is_bundle_root', 'pass_entitlements',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_registrations', 'event_registrations');
    }

    protected static function booted(): void
    {
        self::creating(function (EventRegistration $registration): void {
            if (blank($registration->registration_no)) {
                $prefix = (string) config('events.codes.registration_prefix', 'REG');
                $length = max(6, (int) config('events.codes.registration_length', 10));

                $registration->registration_no = $prefix . '-' . mb_strtoupper(Str::random($length));
            }

            if ($registration->registered_at === null) {
                $registration->registered_at = CarbonImmutable::now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatusState::class,
            'total_participants' => 'integer',
            'total_amount' => 'integer',
            'is_bundle_root' => 'boolean',
            'pass_entitlements' => 'array',
            'registered_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'waitlisted_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        /** @var class-string<Event> $modelClass */
        $modelClass = static::eventModelClass();
        /** @var BelongsTo<Event, $this> $relation */
        $relation = $this->belongsTo($modelClass, 'event_id');

        return $relation;
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function registrant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EventRegistrationParticipant, $this>
     */
    public function participants(): HasMany
    {
        /** @var class-string<EventRegistrationParticipant> $modelClass */
        $modelClass = static::participantModelClass();
        /** @var HasMany<EventRegistrationParticipant, $this> $relation */
        $relation = $this->hasMany($modelClass, 'event_registration_id');

        return $relation;
    }

    /**
     * @return HasOne<EventRegistrationParticipant, $this>
     */
    public function primaryParticipant(): HasOne
    {
        /** @var class-string<EventRegistrationParticipant> $modelClass */
        $modelClass = static::participantModelClass();
        /** @var HasOne<EventRegistrationParticipant, $this> $relation */
        $relation = $this->hasOne($modelClass, 'event_registration_id');

        return $relation->where('is_primary', true);
    }

    /**
     * @return HasMany<EventRegistrationAnswer, $this>
     */
    public function answers(): HasMany
    {
        /** @var class-string<EventRegistrationAnswer> $modelClass */
        $modelClass = static::answerModelClass();
        /** @var HasMany<EventRegistrationAnswer, $this> $relation */
        $relation = $this->hasMany($modelClass, 'event_registration_id');

        return $relation;
    }

    /**
     * @return HasMany<EventRegistrationItem, $this>
     */
    public function items(): HasMany
    {
        /** @var class-string<EventRegistrationItem> $modelClass */
        $modelClass = static::itemModelClass();
        /** @var HasMany<EventRegistrationItem, $this> $relation */
        $relation = $this->hasMany($modelClass, 'event_registration_id');

        return $relation;
    }

    /**
     * @return MorphMany<Pass, $this>
     */
    public function passes(): MorphMany
    {
        return $this->morphMany(Pass::class, 'registration');
    }

    /**
     * @return HasMany<EventAttendance, $this>
     */
    public function attendances(): HasMany
    {
        /** @var class-string<EventAttendance> $modelClass */
        $modelClass = static::attendanceModelClass();
        /** @var HasMany<EventAttendance, $this> $relation */
        $relation = $this->hasMany($modelClass, 'event_registration_id');

        return $relation;
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function parentRegistration(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_registration_id');
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function childRegistrations(): HasMany
    {
        return $this->hasMany(static::class, 'parent_registration_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function getPassEntitlements(): array
    {
        return $this->pass_entitlements ?? [];
    }

    /**
     * @param  Builder<EventRegistration>  $query
     * @return Builder<EventRegistration>
     */
    public function scopeByOrder(Builder $query, Model $order): Builder
    {
        return $query
            ->where('external_order_id', $order->getKey())
            ->where('external_order_type', $order::class);
    }

    public function isPending(): bool
    {
        return $this->status instanceof Pending;
    }

    public function isWaitlisted(): bool
    {
        return $this->status instanceof Waitlisted;
    }

    public function complete(): void
    {
        $this->completed_at = CarbonImmutable::now();
        $this->status->transitionTo(Completed::class);
    }

    /**
     * @return class-string<Event>
     */
    protected static function eventModelClass(): string
    {
        return ModelResolver::eventClass();
    }

    /**
     * @return class-string<EventRegistrationParticipant>
     */
    protected static function participantModelClass(): string
    {
        return EventRegistrationParticipant::class;
    }

    /**
     * @return class-string<EventRegistrationAnswer>
     */
    protected static function answerModelClass(): string
    {
        return EventRegistrationAnswer::class;
    }

    /**
     * @return class-string<EventRegistrationItem>
     */
    protected static function itemModelClass(): string
    {
        return EventRegistrationItem::class;
    }

    /**
     * @return class-string<EventAttendance>
     */
    protected static function attendanceModelClass(): string
    {
        return EventAttendance::class;
    }

    /**
     * @return array<string, string>|string|null
     */
    public function routeNotificationForMail(Notification $notification): array | string | null
    {
        $participant = $this->resolvePrimaryParticipant();

        if ($participant === null) {
            return null;
        }

        $email = $participant->resolveEmail();

        if ($email === null) {
            return null;
        }

        $name = mb_trim((string) $participant->name);

        return $name !== '' ? [$email => $name] : $email;
    }

    public function resolvePrimaryParticipant(): ?EventRegistrationParticipant
    {
        /** @var EventRegistrationParticipant|null $participant */
        $participant = $this->primaryParticipant()->first()
            ?? $this->participants()->orderByDesc('is_primary')->orderBy('created_at')->first();

        return $participant;
    }

    public function resolvePrimaryParticipantName(): ?string
    {
        $participant = $this->resolvePrimaryParticipant();

        if ($participant === null) {
            return null;
        }

        $name = mb_trim((string) $participant->name);

        return $name !== '' ? $name : null;
    }

    public function resolvePrimaryParticipantEmail(): ?string
    {
        return $this->resolvePrimaryParticipant()?->resolveEmail();
    }

    public function resolvePrimaryParticipantPhone(): ?string
    {
        return $this->resolvePrimaryParticipant()?->resolvePhone();
    }

    protected static function newFactory(): EventRegistrationFactory
    {
        return EventRegistrationFactory::new();
    }

    /**
     * Get the effective scope for child records (participants, items, answers).
     * Returns [event_id, event_occurrence_id, event_session_id].
     *
     * @return array<string, string|null>
     */
    public function scopeFields(): array
    {
        return [
            'event_id' => $this->event_id,
            'event_occurrence_id' => $this->event_occurrence_id,
            'event_session_id' => $this->event_session_id,
        ];
    }

    public function promoteFromWaitlist(): void
    {
        $this->waitlisted_at = null;
        $this->status->transitionTo(Pending::class);
    }
}
