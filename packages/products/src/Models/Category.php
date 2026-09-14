<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Products\Concerns\EnforcesOwnerUniqueIdentity;
use AIArmada\Products\Enums\CatalogStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $position
 * @property CatalogStatus $status
 * @property string $visibility
 * @property bool $is_featured
 * @property CarbonImmutable|null $hidden_at
 * @property CarbonImmutable|null $archived_at
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 */
class Category extends Model implements Auditable, HasMedia
{
    use EnforcesOwnerUniqueIdentity;
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasSlug;
    use HasUuids;
    use InteractsWithMedia;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'products.features.owner';

    /**
     * @return list<string>
     */
    protected function uniqueIdentityColumns(): array
    {
        return ['slug'];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function modifyUniqueIdentityQuery(Builder $query): Builder
    {
        return $this->parent_id === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $this->parent_id);
    }

    protected $fillable = [
        'owner_type',
        'owner_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'position',
        'is_featured',
        'status',
        'visibility',
        'hidden_at',
        'archived_at',
        'meta_title',
        'meta_description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => CatalogStatus::class,
            'visibility' => 'string',
            'is_featured' => 'boolean',
            'hidden_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'status' => 'active',
        'visibility' => 'catalog',
        'is_featured' => false,
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['categories'] ?? $prefix . 'categories';
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = false): Builder
    {
        $ownerToScope = $owner;

        if (func_num_args() < 2) {
            $ownerToScope = OwnerContext::CURRENT;
        }

        $includeGlobalToScope = $includeGlobal;

        if (func_num_args() < 3) {
            $includeGlobalToScope = (bool) config('products.features.owner.include_global', false);
        }

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the parent category.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child categories.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * Get all descendant categories recursively.
     *
     * @return HasMany<Category, $this>
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the products in this category.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            Product::class,
            config('products.database.tables.category_product', 'category_product'),
            'category_id',
            'product_id'
        )->withTimestamps();

        $this->applyOwnerScopeToProductsQuery($relation->getQuery());

        return $relation;
    }

    /**
     * Apply owner scoping so category relationships never leak cross-tenant products.
     *
     * The ambient scope is deliberately replaced (not stacked) so products
     * resolve against this category's owner rather than the caller's.
     *
     * @param  Builder<Product>  $query
     */
    protected function applyOwnerScopeToProductsQuery(Builder $query): void
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            return;
        }

        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        $query->withoutOwnerScope();

        $owner = $this->getKey() === null ? OwnerContext::resolve() : $this->owner;

        OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
    }

    // =========================================================================
    // SPATIE MEDIALIBRARY
    // =========================================================================

    public function registerMediaCollections(): void
    {
        /** @var array{mimes?:array<int,string>} $hero */
        $hero = config('products.media.collections.hero', []);
        /** @var array{mimes?:array<int,string>} $icon */
        $icon = config('products.media.collections.icon', []);
        /** @var array{mimes?:array<int,string>} $banner */
        $banner = config('products.media.collections.banner', []);

        $this->addMediaCollection('hero')
            ->singleFile()
            ->acceptsMimeTypes($hero['mimes'] ?? ['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('icon')
            ->singleFile()
            ->acceptsMimeTypes($icon['mimes'] ?? ['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('banner')
            ->singleFile()
            ->acceptsMimeTypes($banner['mimes'] ?? ['image/jpeg', 'image/png', 'image/webp']);
    }

    // =========================================================================
    // SPATIE SLUGGABLE
    // =========================================================================

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan((int) config('products.seo.slug_max_length', 100));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // =========================================================================
    // HIERARCHY HELPERS
    // =========================================================================

    /**
     * Check if this is a root category.
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if this category has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get all ancestors (parents, grandparents, etc.).
     */
    public function getAncestors(): Collection
    {
        $ancestors = collect();
        $visited = [(string) $this->getKey()];
        $category = $this;

        while ($category->parent !== null) {
            $parent = $category->parent;
            $parentKey = (string) $parent->getKey();

            // Stop on revisit so legacy hierarchy cycles terminate.
            if (in_array($parentKey, $visited, true)) {
                break;
            }

            $visited[] = $parentKey;
            $ancestors->push($parent);
            $category = $parent;
        }

        return $ancestors->reverse();
    }

    /**
     * Get the depth of this category in the tree.
     */
    public function getDepth(): int
    {
        return $this->getAncestors()->count();
    }

    /**
     * Get the full path of category names.
     * e.g., "Electronics > Phones > Smartphones"
     */
    public function getFullPath(string $separator = ' > '): string
    {
        $path = $this->getAncestors()
            ->pluck('name')
            ->push($this->name);

        return $path->implode($separator);
    }

    /**
     * Get the full slug path.
     * e.g., "electronics/phones/smartphones"
     */
    public function getFullSlug(): string
    {
        $path = $this->getAncestors()
            ->pluck('slug')
            ->push($this->slug);

        return $path->implode('/');
    }

    /**
     * Get a nested tree of all descendants.
     *
     * @param  list<string>  $visited
     */
    public function getNestedTree(array $visited = []): array
    {
        $visited[] = (string) $this->getKey();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'children' => $this->children
                ->reject(fn ($child) => in_array((string) $child->getKey(), $visited, true))
                ->map(fn ($child) => $child->getNestedTree($visited))
                ->toArray(),
        ];
    }

    // =========================================================================
    // PRODUCT HELPERS
    // =========================================================================

    /**
     * Get the distinct product count including descendants.
     */
    public function getProductCount(bool $includeDescendants = true): int
    {
        if (! $includeDescendants) {
            return $this->products()->count();
        }

        return $this->subtreeProductsQuery()->count();
    }

    /**
     * Get all distinct products including descendants.
     */
    public function getAllProducts(): Collection
    {
        return $this->subtreeProductsQuery()->get()->unique('id');
    }

    /**
     * Collect this category plus all descendant ids iteratively (cycle-safe).
     *
     * @return list<string>
     */
    private function collectSubtreeCategoryIds(): array
    {
        $ids = [(string) $this->getKey()];
        $frontier = [$this->getKey()];

        while ($frontier !== []) {
            /** @var list<string> $childIds */
            $childIds = static::query()
                ->whereIn('parent_id', $frontier)
                ->pluck($this->getKeyName())
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();

            $frontier = [];

            foreach ($childIds as $childId) {
                if (in_array($childId, $ids, true)) {
                    continue;
                }

                $ids[] = $childId;
                $frontier[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @return Builder<Product>
     */
    private function subtreeProductsQuery(): Builder
    {
        $ids = $this->collectSubtreeCategoryIds();

        $query = Product::query()->whereHas('categories', function ($q) use ($ids): void {
            $q->whereKey($ids);
        });

        $this->applyOwnerScopeToProductsQuery($query);

        return $query;
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', CatalogStatus::Active);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeHidden(Builder $query): Builder
    {
        return $query->where('status', CatalogStatus::Hidden);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', CatalogStatus::Archived);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (! (bool) config('products.features.owner.enabled', true)) {
                return;
            }

            $hasOwnerType = $category->owner_type !== null;
            $hasOwnerId = $category->owner_id !== null;

            if ($hasOwnerType !== $hasOwnerId) {
                throw new InvalidArgumentException('Invalid owner columns: owner_type and owner_id must be both set or both null.');
            }

            $owner = OwnerContext::resolve();

            if ($owner !== null && $hasOwnerType && ! $category->belongsToOwner($owner)) {
                throw new InvalidArgumentException('Cross-tenant write blocked: category owner does not match the current owner context.');
            }

            if (! (bool) config('products.features.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($category->owner_type !== null || $category->owner_id !== null) {
                return;
            }

            if ($owner === null) {
                return;
            }

            $category->assignOwner($owner);
        });

        static::saving(function (Category $category): void {
            $category->validateParentHierarchy();
        });

        static::deleting(function (Category $category): void {
            // Nullify parent_id for children
            $category->children()->update(['parent_id' => null]);
            // Detach from products pivot
            $category->products()->detach();
        });
    }

    private function validateParentHierarchy(): void
    {
        if ($this->parent_id === null) {
            return;
        }

        if ($this->exists && ! $this->isDirty('parent_id')) {
            return;
        }

        /** @var Category|null $parent */
        $parent = static::query()->withoutOwnerScope()->whereKey($this->parent_id)->first();

        if ($parent === null) {
            throw new InvalidArgumentException('Invalid parent_id: category not found.');
        }

        if ($this->exists && (string) $parent->getKey() === (string) $this->getKey()) {
            throw new InvalidArgumentException('Invalid parent_id: a category cannot be its own parent.');
        }

        $this->rejectHierarchyCycle($parent);

        if (! (bool) config('products.features.owner.enabled', true)) {
            return;
        }

        $parentGlobal = $parent->owner_type === null && $parent->owner_id === null;
        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        [$selfType, $selfId] = $this->effectiveOwnerTuple();

        $sameOwner = $selfType === $parent->owner_type
            && ($selfId === null || $parent->owner_id === null
                ? $selfId === $parent->owner_id
                : (string) $selfId === (string) $parent->owner_id);

        if (! $sameOwner && ! ($parentGlobal && $includeGlobal)) {
            throw new InvalidArgumentException('Cross-tenant write blocked: category parent does not belong to the same owner.');
        }
    }

    private function rejectHierarchyCycle(Category $parent): void
    {
        if (! $this->exists) {
            return;
        }

        $selfKey = (string) $this->getKey();
        $seen = [(string) $parent->getKey()];
        $cursor = $parent;

        while ($cursor->parent_id !== null) {
            $cursorParentId = (string) $cursor->parent_id;

            if ($cursorParentId === $selfKey) {
                throw new InvalidArgumentException('Invalid parent_id: a category cannot be moved below its own descendant.');
            }

            if (in_array($cursorParentId, $seen, true)) {
                break;
            }

            $seen[] = $cursorParentId;

            /** @var Category|null $cursor */
            $cursor = static::query()->withoutOwnerScope()->whereKey($cursorParentId)->first();

            if ($cursor === null) {
                break;
            }
        }
    }

    /**
     * Owner tuple for hierarchy checks, resolving the ambient context for
     * creates whose auto-assignment has not run yet.
     *
     * @return array{0: string|null, 1: mixed}
     */
    private function effectiveOwnerTuple(): array
    {
        if ($this->owner_type !== null && $this->owner_id !== null) {
            return [$this->owner_type, $this->owner_id];
        }

        if ($this->exists) {
            return [$this->owner_type, $this->owner_id];
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return [null, null];
        }

        return [$owner->getMorphClass(), $owner->getKey()];
    }
}
