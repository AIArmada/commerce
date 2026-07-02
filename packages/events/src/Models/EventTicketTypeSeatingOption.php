<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTicketTypeSeatingOptionFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use AIArmada\Seating\Models\SeatSection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_ticket_type_id
 * @property string|null $seat_section_id
 * @property string|null $seat_category
 * @property int|null $included_quantity
 * @property int|null $allowed_quantity
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventTicketTypeSeatingOption extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_ticket_type_id', 'seat_section_id',
        'seat_category', 'included_quantity', 'allowed_quantity',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_ticket_type_seating_options', 'event_ticket_type_seating_options');
    }

    protected function casts(): array
    {
        return [
            'included_quantity' => 'integer',
            'allowed_quantity' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventTicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'event_ticket_type_id');
    }

    /**
     * @return BelongsTo<SeatSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SeatSection::class, 'seat_section_id');
    }

    protected static function newFactory(): EventTicketTypeSeatingOptionFactory
    {
        return EventTicketTypeSeatingOptionFactory::new();
    }
}
