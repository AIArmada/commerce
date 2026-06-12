<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTimeExpressionFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    use UsesEventUuid;

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
        return config('events.database.tables.event_time_expressions', 'event_time_expressions');
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

    protected static function newFactory(): EventTimeExpressionFactory
    {
        return EventTimeExpressionFactory::new();
    }
}
