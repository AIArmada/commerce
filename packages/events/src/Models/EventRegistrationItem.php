<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventRegistrationItemFactory;
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
 * @property string|null $event_ticket_type_id
 * @property int $quantity
 * @property int $unit_price
 * @property int $total_price
 * @property string $currency
 * @property string|null $external_order_item_id
 * @property string|null $external_order_item_type
 * @property string $status
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 */
final class EventRegistrationItem extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_registration_id',
        'event_id', 'event_occurrence_id', 'event_session_id',
        'event_ticket_type_id',
        'quantity', 'unit_price', 'total_price', 'currency',
        'external_order_item_id', 'external_order_item_type',
        'status',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_registration_items', 'event_registration_items');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total_price' => 'integer',
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

    /** @return BelongsTo<EventRegistration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    /** @return BelongsTo<EventTicketType, $this> */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'event_ticket_type_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function externalOrderItem(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<EventPass, $this> */
    public function passes(): HasMany
    {
        return $this->hasMany(EventPass::class, 'event_registration_item_id');
    }

    protected static function newFactory(): EventRegistrationItemFactory
    {
        return EventRegistrationItemFactory::new();
    }
}
