<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventRegistrationAnswerFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_registration_id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $event_registration_participant_id
 * @property string $field_key
 * @property string|null $question
 * @property string|null $answer
 * @property mixed|null $answer_json
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 */
final class EventRegistrationAnswer extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_registration_id',
        'event_id', 'event_occurrence_id', 'event_session_id',
        'event_registration_participant_id',
        'field_key', 'question', 'answer', 'answer_json',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_registration_answers', 'event_registration_answers');
    }

    protected function casts(): array
    {
        return [
            'answer_json' => 'array',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /** @return BelongsTo<EventSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    /**
     * @return BelongsTo<EventRegistrationParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationParticipant::class, 'event_registration_participant_id');
    }

    protected static function newFactory(): EventRegistrationAnswerFactory
    {
        return EventRegistrationAnswerFactory::new();
    }
}
