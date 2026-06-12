<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTicketTypeComponentFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $parent_ticket_type_id
 * @property string $component_ticket_type_id
 * @property int $quantity
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventTicketTypeComponent extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'parent_ticket_type_id', 'component_ticket_type_id',
        'quantity',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_ticket_type_components', 'event_ticket_type_components');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventTicketType, $this>
     */
    public function parentTicketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'parent_ticket_type_id');
    }

    /**
     * @return BelongsTo<EventTicketType, $this>
     */
    public function componentTicketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'component_ticket_type_id');
    }

    protected static function newFactory(): EventTicketTypeComponentFactory
    {
        return EventTicketTypeComponentFactory::new();
    }
}
