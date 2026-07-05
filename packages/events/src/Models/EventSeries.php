<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Database\Factories\EventSeriesFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $series_type
 * @property string $status
 * @property string $visibility
 * @property bool $is_dynamic
 * @property mixed|null $dynamic_rule_json
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EventSeries extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesEventUuid;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'owner_type', 'owner_id',
        'title', 'slug', 'description',
        'series_type', 'status', 'visibility',
        'is_dynamic', 'dynamic_rule_json',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_series', 'event_series');
    }

    protected function casts(): array
    {
        return [
            'is_dynamic' => 'boolean',
            'dynamic_rule_json' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<EventSeriesItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EventSeriesItem::class);
    }

    /**
     * @return HasMany<EventSeriesRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(EventSeriesRule::class);
    }

    protected static function newFactory(): EventSeriesFactory
    {
        return EventSeriesFactory::new();
    }
}
