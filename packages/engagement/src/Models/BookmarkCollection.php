<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Engagement\Database\Factories\BookmarkCollectionFactory;
use AIArmada\Engagement\Models\Concerns\UsesEngagementUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $description
 * @property string $visibility
 * @property string $status
 * @property int $sort_order
 * @property bool $is_default
 * @property bool $is_system
 * @property CarbonImmutable|null $archived_at
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, BookmarkCollectionItem> $items
 */
final class BookmarkCollection extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesEngagementUuid;

    protected static string $ownerScopeConfigKey = 'engagement.owner';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_LOCKED = 'locked';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_UNLISTED = 'unlisted';

    public const VISIBILITY_PUBLIC = 'public';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'visibility',
        'status',
        'sort_order',
        'is_default',
        'is_system',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('engagement.database.tables.bookmark_collections', 'bookmark_collections');
    }

    /**
     * @return HasMany<BookmarkCollectionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BookmarkCollectionItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_system' => 'boolean',
            'metadata' => 'array',
            'archived_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): BookmarkCollectionFactory
    {
        return BookmarkCollectionFactory::new();
    }
}
