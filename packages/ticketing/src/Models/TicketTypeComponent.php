<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Ticketing\Database\Factories\TicketTypeComponentFactory;
use AIArmada\Ticketing\Support\TicketingOwnerGuard;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $parent_ticket_type_id
 * @property string $component_ticket_type_id
 * @property int $quantity
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TicketType $parentTicketType
 * @property-read TicketType|null $componentTicketType
 */
class TicketTypeComponent extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'ticketing.features.owner';

    protected static function newFactory(): TicketTypeComponentFactory
    {
        return TicketTypeComponentFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $component): void {
            TicketingOwnerGuard::assertRelations($component, [
                ['relation' => 'parentTicketType', 'required' => true],
                ['relation' => 'componentTicketType', 'required' => true],
            ]);
        });
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'quantity' => 1,
    ];

    protected $fillable = [
        'parent_ticket_type_id',
        'component_ticket_type_id',
        'quantity',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('ticketing.database.tables.ticket_type_components', 'ticket_type_components');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function parentTicketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'parent_ticket_type_id');
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function componentTicketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'component_ticket_type_id');
    }
}
