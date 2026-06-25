<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Engagement\Database\Factories\BookmarkCollectionItemFactory;
use AIArmada\Engagement\Models\Concerns\UsesEngagementUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $bookmark_collection_id
 * @property string $bookmark_id
 * @property int $sort_order
 * @property string|null $notes
 * @property CarbonImmutable|null $added_at
 * @property CarbonImmutable|null $removed_at
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read BookmarkCollection $collection
 * @property-read Bookmark $bookmark
 */
final class BookmarkCollectionItem extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesEngagementUuid;

    protected static string $ownerScopeConfigKey = 'engagement.owner';

    protected $fillable = [
        'bookmark_collection_id',
        'bookmark_id',
        'sort_order',
        'notes',
        'added_at',
        'removed_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('engagement.database.tables.bookmark_collection_items', 'bookmark_collection_items');
    }

    /**
     * @return BelongsTo<BookmarkCollection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(BookmarkCollection::class, 'bookmark_collection_id');
    }

    /**
     * @return BelongsTo<Bookmark, $this>
     */
    public function bookmark(): BelongsTo
    {
        return $this->belongsTo(Bookmark::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'added_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): BookmarkCollectionItemFactory
    {
        return BookmarkCollectionItemFactory::new();
    }
}
