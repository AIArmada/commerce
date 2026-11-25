<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Models;

use AIArmada\Events\Contracts\ResolvesEventTimeExpression;
use AIArmada\Events\Data\EventTimeExpressionData;
use AIArmada\Events\Database\Factories\EventTimeExpressionFactory;
use AIArmada\Events\Platform\ScopesByEventOwner;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $time_mode
 * @property string|null $anchor_type
 * @property string|null $anchor_code
 * @property string|null $relation
 * @property int|null $offset_minutes
 * @property string|null $display_label
 * @property string|null $resolver_class
 * @property mixed|null $resolver_context
 * @property CarbonImmutable|null $resolved_starts_at
 * @property CarbonImmutable|null $resolved_ends_at
 * @property CarbonImmutable|null $resolved_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventTimeExpression extends Model
{
    use HasFactory;
    use HasUuids;
    use ScopesByEventOwner;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'time_mode', 'anchor_type', 'anchor_code',
        'relation', 'offset_minutes',
        'display_label',
        'resolver_class', 'resolver_context',
        'resolved_starts_at', 'resolved_ends_at', 'resolved_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_time_expressions', 'event_time_expressions');
    }

    protected function casts(): array
    {
        return [
            'offset_minutes' => 'integer',
            'resolver_context' => 'array',
            'resolved_starts_at' => 'immutable_datetime',
            'resolved_ends_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolveExpression(array $context = []): ?DateTimeInterface
    {
        return app(ResolvesEventTimeExpression::class)->resolve($this->toData(), $context);
    }

    public function toData(): EventTimeExpressionData
    {
        return new EventTimeExpressionData(
            id: (string) $this->getKey(),
            eventId: (string) $this->event_id,
            occurrenceId: $this->event_occurrence_id,
            sessionId: $this->event_session_id,
            timeMode: (string) $this->time_mode,
            anchorType: $this->anchor_type,
            anchorCode: $this->anchor_code,
            relation: $this->relation,
            offsetMinutes: $this->offset_minutes,
            displayLabel: $this->display_label,
            resolverClass: $this->resolver_class,
            resolverContext: is_array($this->resolver_context) ? $this->resolver_context : null,
            resolvedStartsAt: $this->resolved_starts_at,
            resolvedEndsAt: $this->resolved_ends_at,
            resolvedAt: $this->resolved_at,
            metadata: is_array($this->metadata) ? $this->metadata : null,
        );
    }

    protected static function newFactory(): EventTimeExpressionFactory
    {
        return EventTimeExpressionFactory::new();
    }
}
