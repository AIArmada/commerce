<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Contracts\EventAddressable;
use AIArmada\Events\Data\EventAddressData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

class EventOrganizer extends Model implements Auditable, EventAddressable
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
        'display_name', 'logo_url', 'contact_email', 'website_url',
        'order_column', 'metadata',
    ];

    protected function casts(): array
    {
        return ['order_column' => 'integer', 'metadata' => 'array'];
    }

    public function getTable(): string
    {
        return config('events.database.tables.organizers', 'event_organizers');
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function eventAddressData(): EventAddressData
    {
        return new EventAddressData(label: $this->display_name ?? 'Organizer', lines: []);
    }
}
