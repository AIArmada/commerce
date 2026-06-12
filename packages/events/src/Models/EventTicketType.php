<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTicketTypeFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string $access_type
 * @property string|null $seating_mode
 * @property int $price
 * @property string $currency
 * @property int|null $quota
 * @property int $admits_quantity
 * @property int|null $min_quantity
 * @property int|null $max_quantity
 * @property CarbonImmutable|null $sales_starts_at
 * @property CarbonImmutable|null $sales_ends_at
 * @property string $status
 * @property string $visibility
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 */
final class EventTicketType extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'name', 'code', 'description',
        'access_type', 'seating_mode',
        'price', 'currency',
        'quota', 'admits_quantity', 'min_quantity', 'max_quantity',
        'sales_starts_at', 'sales_ends_at',
        'status', 'visibility', 'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_ticket_types', 'event_ticket_types');
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quota' => 'integer',
            'admits_quantity' => 'integer',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'sales_starts_at' => 'immutable_datetime',
            'sales_ends_at' => 'immutable_datetime',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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
     * @return HasMany<EventRegistrationItem, $this>
     */
    public function registrationItems(): HasMany
    {
        return $this->hasMany(EventRegistrationItem::class, 'event_ticket_type_id');
    }

    /**
     * @return HasMany<EventPass, $this>
     */
    public function passes(): HasMany
    {
        return $this->hasMany(EventPass::class, 'event_ticket_type_id');
    }

    /**
     * @return HasMany<EventTicketTypeComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(EventTicketTypeComponent::class, 'parent_ticket_type_id');
    }

    /**
     * @return HasMany<EventTicketTypeSeatingOption, $this>
     */
    public function seatingOptions(): HasMany
    {
        return $this->hasMany(EventTicketTypeSeatingOption::class, 'event_ticket_type_id');
    }

    protected static function newFactory(): EventTicketTypeFactory
    {
        return EventTicketTypeFactory::new();
    }
}
