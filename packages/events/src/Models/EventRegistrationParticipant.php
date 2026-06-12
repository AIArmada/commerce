<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventRegistrationParticipantFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $relationship_to_registrant
 * @property bool $is_primary
 * @property int|null $age
 * @property string|null $gender
 * @property string $status
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventRegistration $registration
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 */
final class EventRegistrationParticipant extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_registration_id',
        'event_id', 'event_occurrence_id', 'event_session_id',
        'participant_type', 'participant_id',
        'name', 'email', 'phone',
        'relationship_to_registrant', 'is_primary',
        'age', 'gender',
        'status', 'notes',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_registration_participants', 'event_registration_participants');
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'age' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function participant(): MorphTo
    {
        return $this->morphTo();
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
     * @return HasMany<EventRegistrationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(EventRegistrationAnswer::class, 'event_registration_participant_id');
    }

    /**
     * @return HasMany<EventPass, $this>
     */
    public function passes(): HasMany
    {
        return $this->hasMany(EventPass::class, 'event_registration_participant_id');
    }

    /**
     * @return HasMany<EventAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class, 'event_registration_participant_id');
    }

    /**
     * @return HasMany<EventSeatAllocation, $this>
     */
    public function seatAllocations(): HasMany
    {
        return $this->hasMany(EventSeatAllocation::class, 'event_registration_participant_id');
    }

    protected static function newFactory(): EventRegistrationParticipantFactory
    {
        return EventRegistrationParticipantFactory::new();
    }
}
