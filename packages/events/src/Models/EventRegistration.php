<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventRegistrationFactory;
use AIArmada\Events\Models\Concerns\ScopesByEventOwner;
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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
use InvalidArgumentException;
use LogicException;
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
 * @property CarbonImmutable|null $refund_pending_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $last_state_change_at
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
    use HasUuids;
    use Notifiable;
    use ScopesByEventOwner;

    public const array CAPACITY_BLOCKING_STATUSES = [
        'pending',
        'confirmed',
        'refund_pending',
        'checked_in',
    ];

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'registrant_type', 'registrant_id',
        'registration_no', 'registration_type', 'source',
        'total_participants', 'total_amount', 'currency',
        'external_order_id', 'external_order_type', 'payment_status',
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

                $registration->registration_no = $prefix . '-' . mb_strtoupper((string) $registration->getKey());
            }

            $now = CarbonImmutable::now();

            if ($registration->registered_at === null) {
                $registration->registered_at = $now;
            }

            if ($registration->last_state_change_at === null) {
                $registration->last_state_change_at = $now;
            }

            $status = $registration->getAttribute('status');

            if ($status instanceof RegistrationStatusState) {
                $registration->applyTransitionTimestamp($status::class, $now);
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
            'refund_pending_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'last_state_change_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Set the initial registration state before the model is persisted.
     *
     * Any registered state may be used for an explicit import or fixture
     * creation. Every later change must use transitionStatus() so its
     * lifecycle timestamp is recorded together with the state transition.
     */
    public function initializeStatus(RegistrationStatusState | string $status): static
    {
        if ($this->exists) {
            throw new LogicException('An existing registration must use transitionStatus().');
        }

        $stateClass = $this->resolveRegistrationState($status);

        $now = CarbonImmutable::now();
        $this->registered_at ??= $now;
        $this->setAttribute('status', $stateClass);
        $this->applyTransitionTimestamp($stateClass, $now, true);
        $this->last_state_change_at ??= $now;

        return $this;
    }

    /**
     * Transition a persisted registration and record its lifecycle time.
     *
     * Set `$overwriteLifecycleTimestamp` to false when returning from a
     * temporary state, so the original lifecycle timestamp remains intact.
     */
    public function transitionStatus(RegistrationStatusState | string $status, bool $overwriteLifecycleTimestamp = true): static
    {
        if (! $this->exists) {
            throw new LogicException('A new registration must use initializeStatus().');
        }

        $stateClass = $this->resolveRegistrationState($status);
        $currentState = $this->getAttribute('status');

        if (! $currentState instanceof RegistrationStatusState) {
            throw new LogicException('A persisted registration must have a valid status before it can transition.');
        }

        if ($currentState->equals($stateClass)) {
            $now = CarbonImmutable::now();
            $this->applyTransitionTimestamp($stateClass, $now);
            $this->last_state_change_at ??= $now;

            if ($this->isDirty()) {
                $this->save();
            }

            return $this;
        }

        if (! $currentState->canTransitionTo($stateClass)) {
            throw new LogicException(sprintf(
                'The registration cannot transition from [%s] to [%s].',
                $currentState->getValue(),
                $stateClass::name(),
            ));
        }

        $now = CarbonImmutable::now();
        $this->applyTransitionTimestamp($stateClass, $now, $overwriteLifecycleTimestamp);
        $this->last_state_change_at = $now;
        $currentState->transitionTo($stateClass);

        return $this;
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
        $this->transitionStatus(Completed::class);
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
        $this->transitionStatus(Pending::class);
    }

    /**
     * @return class-string<RegistrationStatusState>
     */
    private function resolveRegistrationState(RegistrationStatusState | string $status): string
    {
        $stateClass = RegistrationStatusState::resolveStateClass($status);

        if (! is_string($stateClass)
            || ! is_a($stateClass, RegistrationStatusState::class, true)
            || ! in_array($stateClass, RegistrationStatusState::getStateMapping()->all(), true)) {
            $statusName = $status instanceof RegistrationStatusState ? $status::name() : $status;

            throw new InvalidArgumentException(sprintf('The registration state [%s] is invalid.', $statusName));
        }

        return $stateClass;
    }

    private function applyTransitionTimestamp(string $stateClass, CarbonImmutable $at, bool $overwrite = false): void
    {
        $attribute = match ($stateClass::name()) {
            'confirmed' => 'approved_at',
            'completed' => 'completed_at',
            'cancelled' => 'cancelled_at',
            'rejected' => 'rejected_at',
            'waitlisted' => 'waitlisted_at',
            'refund_pending' => 'refund_pending_at',
            'refunded' => 'refunded_at',
            'expired' => 'expired_at',
            default => null,
        };

        if ($attribute === null) {
            return;
        }

        if ($overwrite || $this->getAttribute($attribute) === null) {
            $this->setAttribute($attribute, $at);
        }
    }
}
