<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventTicketTypeProductFactory;
use AIArmada\Events\Enums\BundleInclusionMode;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $event_ticket_type_id
 * @property string|null $product_id
 * @property string|null $variant_id
 * @property int $quantity
 * @property BundleInclusionMode $inclusion_mode
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EventTicketType $ticketType
 * @property-read Model|null $product
 * @property-read Model|null $variant
 */
final class EventTicketTypeProduct extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_ticket_type_id',
        'product_type', 'product_id',
        'variant_type', 'variant_id',
        'quantity', 'inclusion_mode', 'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_ticket_type_products', 'event_ticket_type_products');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'inclusion_mode' => BundleInclusionMode::class,
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (EventTicketTypeProduct $model): void {
            if ($model->product_id === null && $model->variant_id === null) {
                throw new InvalidArgumentException('At least one of product_id or variant_id must be set.');
            }
        });
    }

    /**
     * @return BelongsTo<EventTicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'event_ticket_type_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function product(): MorphTo
    {
        /** @var MorphTo<Model, $this> $relation */
        $relation = $this->morphTo(__FUNCTION__, 'product_type', 'product_id');

        return $relation;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function variant(): MorphTo
    {
        /** @var MorphTo<Model, $this> $relation */
        $relation = $this->morphTo(__FUNCTION__, 'variant_type', 'variant_id');

        return $relation;
    }

    protected static function newFactory(): EventTicketTypeProductFactory
    {
        return EventTicketTypeProductFactory::new();
    }
}
