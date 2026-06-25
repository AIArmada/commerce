<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Engagement\Database\Factories\BookmarkFactory;
use AIArmada\Engagement\Models\Concerns\UsesEngagementUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $bookmarker_type
 * @property string $bookmarker_id
 * @property string $bookmarkable_type
 * @property string $bookmarkable_id
 * @property string $status
 * @property string|null $notes
 * @property CarbonImmutable|null $bookmarked_at
 * @property CarbonImmutable|null $removed_at
 * @property CarbonImmutable|null $archived_at
 * @property string|null $source
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, BookmarkCollectionItem> $collectionItems
 */
final class Bookmark extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesEngagementUuid;

    protected static string $ownerScopeConfigKey = 'engagement.owner';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REMOVED = 'removed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'bookmarker_type',
        'bookmarker_id',
        'bookmarkable_type',
        'bookmarkable_id',
        'status',
        'notes',
        'bookmarked_at',
        'removed_at',
        'archived_at',
        'source',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('engagement.database.tables.bookmarks', 'bookmarks');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function bookmarker(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<BookmarkCollectionItem, $this>
     */
    public function collectionItems(): HasMany
    {
        return $this->hasMany(BookmarkCollectionItem::class);
    }

    /**
     * @param  Builder<Bookmark>  $query
     * @return Builder<Bookmark>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<Bookmark>  $query
     * @return Builder<Bookmark>
     */
    public function scopeRemoved(Builder $query): Builder
    {
        return $query->where('status', 'removed');
    }

    /**
     * @param  Builder<Bookmark>  $query
     * @return Builder<Bookmark>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    /**
     * @param  Builder<Bookmark>  $query
     * @return Builder<Bookmark>
     */
    public function scopeForBookmarker(Builder $query, Model $bookmarker): Builder
    {
        return $query->where('bookmarker_type', $bookmarker->getMorphClass())
            ->where('bookmarker_id', $bookmarker->getKey());
    }

    /**
     * @param  Builder<Bookmark>  $query
     * @return Builder<Bookmark>
     */
    public function scopeForBookmarkable(Builder $query, Model $bookmarkable): Builder
    {
        return $query->where('bookmarkable_type', $bookmarkable->getMorphClass())
            ->where('bookmarkable_id', $bookmarkable->getKey());
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRemoved(): bool
    {
        return $this->status === self::STATUS_REMOVED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    protected static function newFactory(): BookmarkFactory
    {
        return BookmarkFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'bookmarked_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
