<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Enums\EventVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

class EventSpeaker extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'assignable_type', 'assignable_id',
        'person_type', 'person_id',
        'display_name', 'biography', 'role_key',
        'order_column', 'visibility', 'metadata',
    ];

    protected $attributes = ['visibility' => 'public'];

    protected function casts(): array
    {
        return [
            'visibility' => EventVisibility::class,
            'order_column' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('events.database.tables.speakers', 'event_speakers');
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function person(): MorphTo
    {
        return $this->morphTo();
    }
}
