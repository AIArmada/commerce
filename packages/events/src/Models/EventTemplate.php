<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Database\Factories\EventTemplateFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $templateable_type
 * @property string|null $templateable_id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property string $template_type
 * @property string $status
 * @property string $visibility
 * @property array $payload
 * @property array|null $default_scope
 * @property string|null $created_by_type
 * @property string|null $created_by_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $archived_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $owner
 * @property-read Model|\Eloquent $templateable
 * @property-read Collection<int, EventTemplateItem> $items
 */
final class EventTemplate extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasFactory;
    use UsesEventUuid;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'owner_type', 'owner_id',
        'templateable_type', 'templateable_id',
        'code', 'name', 'description',
        'template_type', 'status', 'visibility',
        'payload', 'default_scope',
        'created_by_type', 'created_by_id',
        'published_at', 'archived_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_templates', 'event_templates');
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'payload' => 'array',
            'default_scope' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function templateable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EventTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EventTemplateItem::class, 'event_template_id');
    }

    protected static function newFactory(): EventTemplateFactory
    {
        return EventTemplateFactory::new();
    }
}
