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

class EventSponsor extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'assignable_type', 'assignable_id',
        'reference_type', 'reference_id',
        'tier', 'display_name', 'logo_url', 'website_url',
        'order_column', 'metadata',
    ];

    protected function casts(): array
    {
        return ['order_column' => 'integer', 'metadata' => 'array'];
    }

    public function getTable(): string
    {
        return config('events.database.tables.sponsors', 'event_sponsors');
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
