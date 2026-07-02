<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Models;

use AIArmada\Ticketing\Database\Factories\TicketTypeProductFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $ticket_type_id
 * @property string|null $product_type
 * @property string|null $product_id
 * @property string|null $variant_type
 * @property string|null $variant_id
 * @property int $quantity
 * @property string $inclusion_mode
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TicketType|null $ticketType
 * @property-read Model|Eloquent|null $product
 * @property-read Model|Eloquent|null $variant
 */
class TicketTypeProduct extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): TicketTypeProductFactory
    {
        return TicketTypeProductFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'quantity' => 1,
        'inclusion_mode' => 'required',
        'sort_order' => 0,
    ];

    protected $fillable = [
        'ticket_type_id',
        'product_type',
        'product_id',
        'variant_type',
        'variant_id',
        'quantity',
        'inclusion_mode',
        'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('ticketing.database.tables.ticket_type_products', 'ticket_type_products');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
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
     * @return MorphTo<Model, $this>
     */
    public function product(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function variant(): MorphTo
    {
        return $this->morphTo();
    }
}
