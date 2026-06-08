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

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $assignable_type
 * @property string|null $assignable_id
 * @property string|null $asset_type
 * @property string|null $asset_id
 * @property string $role_key
 * @property string|null $provider
 * @property string|null $provider_reference
 * @property string|null $url
 * @property string|null $title
 * @property string|null $alt_text
 * @property string $visibility
 * @property int|null $order_column
 * @property array<string, mixed>|null $metadata
 */
class EventAsset extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'assignable_type',
        'assignable_id',
        'asset_type',
        'asset_id',
        'role_key',
        'provider',
        'provider_reference',
        'url',
        'title',
        'alt_text',
        'visibility',
        'order_column',
        'metadata',
    ];

    protected $attributes = [
        'visibility' => 'public',
    ];

    protected function casts(): array
    {
        return [
            'order_column' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('events.database.tables.assets', 'event_assets');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'assignable_type', 'assignable_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function asset(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'asset_type', 'asset_id');
    }

    public function isPublic(): bool
    {
        return mb_strtolower($this->visibility) !== 'private';
    }
}
