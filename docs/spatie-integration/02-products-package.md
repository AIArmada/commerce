# Products Package: Spatie Integration Blueprint

> **Package:** `aiarmada/products`  
> **Status:** Planned (Vision Only)  
> **Role:** Core Layer - Product Catalog

---

## 📋 Current Vision State

From [products vision docs](../../packages/products/docs/vision/):

- Product catalog with variants
- Categories and collections
- Multi-media support
- SEO-friendly URLs
- Multi-language ready

---

## 🎯 Recommended Spatie Integrations

### Priority 1: laravel-medialibrary (CRITICAL)

**Impact:** Transforms product image/video handling

```json
{
    "require": {
        "spatie/laravel-medialibrary": "^11.10"
    }
}
```

#### Why Critical

- Products are media-heavy (images, videos, 360° views)
- Variants need their own media
- Categories need hero images
- Collections need banners

#### Integration Blueprint

```php
// products/src/Models/Product.php

namespace AIArmada\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useFallbackUrl('/images/product-placeholder.jpg')
            ->useFallbackPath(public_path('/images/product-placeholder.jpg'));

        $this->addMediaCollection('hero')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('videos')
            ->acceptsMimeTypes(['video/mp4', 'video/webm']);

        $this->addMediaCollection('documents')
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->optimize()
            ->queued();

        $this->addMediaConversion('card')
            ->width(400)
            ->height(400)
            ->optimize()
            ->queued();

        $this->addMediaConversion('detail')
            ->width(800)
            ->height(800)
            ->optimize()
            ->queued();

        $this->addMediaConversion('zoom')
            ->width(1600)
            ->height(1600)
            ->optimize()
            ->queued();

        // WebP versions for modern browsers
        $this->addMediaConversion('webp-card')
            ->width(400)
            ->height(400)
            ->format('webp')
            ->optimize()
            ->queued();
    }
}
```

#### Variant Media

```php
// products/src/Models/ProductVariant.php

class ProductVariant extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        // Variant can override product images
        $this->addMediaCollection('variant_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Get images - variant specific or fall back to product
     */
    public function getDisplayImagesAttribute(): Collection
    {
        $variantImages = $this->getMedia('variant_images');
        
        if ($variantImages->isNotEmpty()) {
            return $variantImages;
        }

        return $this->product->getMedia('gallery');
    }
}
```

#### Category/Collection Media

```php
// products/src/Models/Category.php

class Category extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')
            ->singleFile();

        $this->addMediaCollection('icon')
            ->singleFile();

        $this->addMediaCollection('banner')
            ->singleFile();
    }
}
```

#### Value Delivered

- ✅ Automatic image optimization (WebP, quality)
- ✅ Responsive images with srcset
- ✅ Queue-based conversions (non-blocking)
- ✅ S3/cloud storage ready
- ✅ Fallback images
- ✅ Video support
- ✅ PDF documents (spec sheets)

---

### Priority 2: laravel-sluggable (HIGH)

**Impact:** SEO-friendly product URLs

```json
{
    "require": {
        "spatie/laravel-sluggable": "^3.7"
    }
}
```

#### Integration Blueprint

```php
// products/src/Models/Product.php

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model
{
    use HasSlug;

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate() // Preserve SEO URLs
            ->slugsShouldBeNoLongerThan(100);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

```php
// products/src/Models/Category.php

class Category extends Model
{
    use HasSlug;

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->usingLanguage(app()->getLocale());
    }

    /**
     * Generate full path slug: /electronics/phones/smartphones
     */
    public function getFullSlugAttribute(): string
    {
        $segments = $this->ancestors->pluck('slug')->push($this->slug);
        return $segments->implode('/');
    }
}
```

#### Value Delivered

- ✅ Auto-generated SEO URLs
- ✅ Unique slug enforcement
- ✅ Language-aware slugs
- ✅ Hierarchical category paths
- ✅ Route model binding

---

### Priority 3: laravel-tags (HIGH)

**Impact:** Flexible product tagging and categorization

```json
{
    "require": {
        "spatie/laravel-tags": "^4.10"
    }
}
```

#### Integration Blueprint

```php
// products/src/Models/Product.php

use Spatie\Tags\HasTags;

class Product extends Model
{
    use HasTags;
}
```

```php
// Usage examples

// Basic tagging
$product->attachTags(['new', 'featured', 'sale']);

// Typed tags for different purposes
$product->attachTags(['red', 'blue', 'green'], 'colors');
$product->attachTags(['summer', 'winter'], 'seasons');
$product->attachTags(['small', 'medium', 'large'], 'sizes');

// Query by tags
Product::withAnyTags(['new', 'featured'])->get();
Product::withAllTags(['red', 'summer'])->get();
Product::withoutTags(['discontinued'])->get();

// Get products by color
Product::withAnyTagsOfType(['red', 'blue'], 'colors')->get();
```

#### Tag Types for Commerce

| Tag Type | Purpose | Examples |
|----------|---------|----------|
| `default` | General labels | new, featured, sale |
| `colors` | Color variants | red, blue, green |
| `sizes` | Size options | small, medium, large |
| `materials` | Material types | cotton, polyester |
| `seasons` | Seasonal products | summer, winter |
| `occasions` | Use cases | casual, formal |

#### Value Delivered

- ✅ Flexible product attributes
- ✅ Multi-type tagging
- ✅ Translatable tags
- ✅ Tag ordering
- ✅ Efficient querying

---

### Priority 4: laravel-translatable (HIGH)

**Impact:** Multi-language product catalog

```json
{
    "require": {
        "spatie/laravel-translatable": "^6.12"
    }
}
```

#### Integration Blueprint

```php
// products/src/Models/Product.php

use Spatie\Translatable\HasTranslations;
use Spatie\Sluggable\HasTranslatableSlug;

class Product extends Model
{
    use HasTranslations;
    use HasTranslatableSlug;

    public $translatable = [
        'name',
        'description',
        'short_description',
        'meta_title',
        'meta_description',
        'slug',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}
```

```php
// Usage examples

// Set translations
$product->setTranslation('name', 'en', 'Laptop Computer');
$product->setTranslation('name', 'ms', 'Komputer Riba');
$product->setTranslation('name', 'zh', '笔记本电脑');

// Get translation
$product->name; // Returns based on app()->getLocale()
$product->getTranslation('name', 'ms'); // Force specific locale

// Query in specific locale
Product::whereLocale('name', 'ms')->get();
```

#### Value Delivered

- ✅ JSON-based translations (no separate tables)
- ✅ Translatable slugs for SEO
- ✅ Locale-aware queries
- ✅ Easy Filament integration
- ✅ Import/export friendly

---

### Priority 5: laravel-activitylog (Inherited)

Already in commerce-support, but products should leverage:

```php
// products/src/Models/Product.php

use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Product extends Model
{
    use LogsCommerceActivity;

    protected function getLoggableAttributes(): array
    {
        return [
            'name',
            'sku',
            'price',
            'status',
            'stock_status',
        ];
    }

    protected function getActivityLogName(): string
    {
        return 'products';
    }
}
```

---

## 📦 Full composer.json Blueprint

```json
{
    "name": "aiarmada/products",
    "description": "Product catalog management for AIArmada Commerce.",
    "require": {
        "php": "^8.4",
        "aiarmada/commerce-support": "^1.0",
        "spatie/laravel-medialibrary": "^11.10",
        "spatie/laravel-sluggable": "^3.7",
        "spatie/laravel-tags": "^4.10",
        "spatie/laravel-translatable": "^6.12"
    },
    "suggest": {
        "spatie/laravel-query-builder": "For API query filtering",
        "spatie/laravel-searchable": "For product search"
    }
}
```

---

## 🏗️ Model Integration Summary

```php
// products/src/Models/Product.php - COMPLETE INTEGRATION

namespace AIArmada\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasTranslatableSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Tags\HasTags;
use Spatie\Translatable\HasTranslations;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Contracts\BuyableInterface;

class Product extends Model implements HasMedia, BuyableInterface
{
    use HasUuids;
    use InteractsWithMedia;
    use HasTranslatableSlug;
    use HasTags;
    use HasTranslations;
    use LogsCommerceActivity;

    protected $fillable = [
        'name',
        'description',
        'short_description',
        'sku',
        'price',
        'compare_price',
        'cost',
        'status',
        'meta_title',
        'meta_description',
    ];

    public $translatable = [
        'name',
        'description', 
        'short_description',
        'meta_title',
        'meta_description',
        'slug',
    ];

    // Spatie Media
    public function registerMediaCollections(): void { ... }
    public function registerMediaConversions(?Media $media = null): void { ... }

    // Spatie Sluggable
    public function getSlugOptions(): SlugOptions { ... }
    public function getRouteKeyName(): string { return 'slug'; }

    // Commerce Activity Log
    protected function getLoggableAttributes(): array { ... }
    protected function getActivityLogName(): string { return 'products'; }

    // BuyableInterface implementation
    public function getBuyableIdentifier(): string { return $this->id; }
    public function getBuyableDescription(): string { return $this->name; }
    public function getBuyablePrice(): Money { ... }
}
```

---

## 📊 Feature Comparison

| Feature | Without Spatie | With Spatie |
|---------|---------------|-------------|
| Image Management | Custom, 500+ lines | 50 lines with MediaLibrary |
| Image Conversions | Manual ImageMagick | Automatic, queued |
| URL Slugs | Custom trait, 100 lines | 10 lines with Sluggable |
| Product Tags | Custom pivot tables | HasTags trait |
| Translations | Separate tables, complex | JSON column, simple |
| Audit Trail | Manual logging | Automatic with ActivityLog |

---

## 🔗 Filament Integration

```php
// filament-products/src/Resources/ProductResource.php

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\SpatieTagsColumn;

class ProductResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            // Media upload with drag-drop
            SpatieMediaLibraryFileUpload::make('gallery')
                ->collection('gallery')
                ->multiple()
                ->reorderable()
                ->responsiveImages(),

            // Tag input with autocomplete
            SpatieTagsInput::make('tags'),

            // Typed tags
            SpatieTagsInput::make('colors')
                ->type('colors'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            // Thumbnail column
            SpatieMediaLibraryImageColumn::make('hero')
                ->collection('hero')
                ->circular(),

            // Tags column
            SpatieTagsColumn::make('tags'),
        ]);
    }
}
```

---

## ✅ Implementation Checklist

### Phase 1: Media Library

- [ ] Install spatie/laravel-medialibrary
- [ ] Run media library migrations
- [ ] Add HasMedia to Product model
- [ ] Configure media collections
- [ ] Configure media conversions
- [ ] Set up S3 disk for production
- [ ] Add Filament media components

### Phase 2: Sluggable + Translatable

- [ ] Install spatie/laravel-sluggable
- [ ] Install spatie/laravel-translatable
- [ ] Add traits to models
- [ ] Migrate existing slugs
- [ ] Update routes for slug binding
- [ ] Add locale switcher in Filament

### Phase 3: Tags

- [ ] Install spatie/laravel-tags
- [ ] Run tags migrations
- [ ] Add HasTags to Product model
- [ ] Define tag types
- [ ] Add Filament tag components
- [ ] Create tag management page

### Phase 4: Activity Log (Inherited)

- [ ] Add LogsCommerceActivity trait
- [ ] Configure loggable attributes
- [ ] Add activity timeline in Filament

---

## 🔗 Related Documents

- [00-overview.md](00-overview.md) - Master integration overview
- [01-commerce-support.md](01-commerce-support.md) - Foundation layer
- [04-orders-package.md](04-orders-package.md) - Order integration with products
- [06-inventory-package.md](06-inventory-package.md) - Inventory tracking

---

*This blueprint was created by the Visionary Chief Architect.*
