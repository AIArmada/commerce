<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

class EventSeatCategory extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'assignable_type', 'assignable_id',
        'name', 'capacity', 'product_id', 'variant_id',
        'order_column', 'metadata',
    ];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'order_column' => 'integer', 'metadata' => 'array'];
    }

    public function getTable(): string
    {
        return config('events.database.tables.seat_categories', 'event_seat_categories');
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}
