<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Models;

use AIArmada\Events\Database\Factories\EventRegistrationAnswerFactory;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Events\Platform\ScopesByEventOwner;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
 * @property-read Model $event
 * @property-read Model|null $occurrence
 * @property-read Model|null $session
 */
final class EventRegistrationAnswer extends Model
{
    use HasFactory;
    use HasUuids;
    use ScopesByEventOwner;

    protected $fillable = [
        'event_registration_id',
        'event_id', 'event_occurrence_id', 'event_session_id',
        'event_registration_participant_id',
        'field_key', 'question', 'answer', 'answer_json',
        'metadata',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_registration_answers', 'event_registration_answers');
    }

    protected function casts(): array
    {
        return [
            'answer_json' => 'array',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function event(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::eventClass();

        return $this->belongsTo($modelClass, 'event_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function occurrence(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::occurrenceClass();

        return $this->belongsTo($modelClass, 'event_occurrence_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function session(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::sessionClass();

        return $this->belongsTo($modelClass, 'event_session_id');
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
     * @return BelongsTo<Model, $this>
     */
    public function participant(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::registrationParticipantClass();

        return $this->belongsTo($modelClass, 'event_registration_participant_id');
    }

    protected static function newFactory(): EventRegistrationAnswerFactory
    {
        return EventRegistrationAnswerFactory::new();
    }
}
