<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventRegistrationQuestionFactory;
use AIArmada\Events\Enums\EventRegistrationQuestionStatus;
use AIArmada\Events\Enums\EventRegistrationQuestionType;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use AIArmada\Events\Support\ModelResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * A reusable participant question definition.
 *
 * Questions are scoped to an event, occurrence, or session. Answers retain a
 * snapshot of the question text, so editing or archiving a question never
 * changes historical registration records.
 *
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $field_key
 * @property string $question
 * @property string|null $description
 * @property EventRegistrationQuestionType $type
 * @property array<int, string>|null $options
 * @property bool $is_required
 * @property EventRegistrationQuestionStatus $status
 * @property Carbon|null $archived_at
 * @property int|null $order_column
 * @property array<string, mixed>|null $metadata
 */
class EventRegistrationQuestion extends Model implements Sortable
{
    use HasFactory;
    use SortableTrait;
    use UsesEventUuid;

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected $fillable = [
        'event_id',
        'event_occurrence_id',
        'event_session_id',
        'field_key',
        'question',
        'description',
        'type',
        'options',
        'is_required',
        'status',
        'archived_at',
        'order_column',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_registration_questions', 'event_registration_questions');
    }

    protected function casts(): array
    {
        return [
            'type' => EventRegistrationQuestionType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'status' => EventRegistrationQuestionStatus::class,
            'archived_at' => 'immutable_datetime',
            'order_column' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EventRegistrationQuestionStatus::Active->value);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::eventClass(), 'event_id');
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
     * Keep question ordering independent for each event/occurrence/session
     * scope. Without this, creating a question for one event could continue
     * the global order sequence from another event.
     */
    public function buildSortQuery(): Builder
    {
        $query = static::query()
            ->where('event_id', $this->event_id)
            ->where(function (Builder $scope): void {
                $scope->where('event_occurrence_id', $this->event_occurrence_id)
                    ->where('event_session_id', $this->event_session_id);
            });

        return $query;
    }

    protected static function newFactory(): Factory
    {
        return EventRegistrationQuestionFactory::new();
    }
}
