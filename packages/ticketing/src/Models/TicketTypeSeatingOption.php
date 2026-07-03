<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Models;

use AIArmada\Seating\Models\SeatSection;
use AIArmada\Ticketing\Database\Factories\TicketTypeSeatingOptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $ticket_type_id
 * @property string|null $seat_section_id
 * @property string|null $seat_category
 * @property int|null $included_quantity
 * @property int|null $allowed_quantity
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TicketType $ticketType
 * @property-read SeatSection|null $section
 */
class TicketTypeSeatingOption extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): TicketTypeSeatingOptionFactory
    {
        return TicketTypeSeatingOptionFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ticket_type_id',
        'seat_section_id',
        'seat_category',
        'included_quantity',
        'allowed_quantity',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('ticketing.database.tables.ticket_type_seating_options', 'ticket_type_seating_options');
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
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * @return BelongsTo<SeatSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SeatSection::class, 'seat_section_id');
    }
}
