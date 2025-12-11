<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Category extends Model implements HasMedia
{
    use HasFactory;
    use HasOwner;
    use HasSlug;
    use HasUuids;
    use InteractsWithMedia;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array<int, string>
     */
    protected $attributes = [
        'position' => 0,
        'is_visible' => true,
        'is_featured' => false,
    ];

    public function getTable(): string
    {
        return config('products.tables.categories', 'product_categories');
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
        return $this->belongsToMany(
            Product::class,
            config('products.tables.category_product', 'category_product'),
            'category_id',
            'product_id'
        )->withTimestamps();
    }

    // =========================================================================
    // SPATIE MEDIALIBRARY
    // =========================================================================

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')
            ->singleFile();

        $this->addMediaCollection('icon')
            ->singleFile();

        $this->addMediaCollection('banner')
            ->singleFile();
    }

    // =========================================================================
    // SPATIE SLUGGABLE
    // =========================================================================

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
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
        $category = $this;

        while ($category->parent !== null) {
            $ancestors->push($category->parent);
            $category = $category->parent;
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
     */
    public function getNestedTree(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'children' => $this->children->map(fn ($child) => $child->getNestedTree())->toArray(),
        ];
    }

    // =========================================================================
    // PRODUCT HELPERS
    // =========================================================================

    /**
     * Get total product count including descendants.
     */
    public function getProductCount(bool $includeDescendants = true): int
    {
        $count = $this->products()->count();

        if ($includeDescendants) {
            foreach ($this->children as $child) {
                $count += $child->getProductCount(true);
            }
        }

        return $count;
    }

    /**
     * Get all products including descendants.
     */
    public function getAllProducts(): Collection
    {
        $products = $this->products;

        foreach ($this->descendants as $descendant) {
            $products = $products->merge($descendant->products);
        }

        return $products->unique('id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::deleting(function (Category $category): void {
            // Nullify parent_id for children
            $category->children()->update(['parent_id' => null]);
            // Detach from products pivot
            $category->products()->detach();
        });
    }
}
