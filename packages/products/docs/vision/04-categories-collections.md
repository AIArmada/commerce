# Categories & Collections

> **Document:** 04 of 08  
> **Package:** `aiarmada/products`  
> **Status:** Vision

---

## Overview

Products are organized through two complementary systems:
- **Categories**: Hierarchical taxonomy for navigation and SEO
- **Collections**: Flexible groupings for merchandising and promotions

---

## Category System

### Hierarchical Structure

```
Electronics
├── Computers
│   ├── Laptops
│   │   ├── Gaming Laptops
│   │   └── Business Laptops
│   └── Desktops
├── Mobile Devices
│   ├── Smartphones
│   └── Tablets
└── Accessories
    ├── Cables
    └── Cases
```

### Category Model

```php
namespace AIArmada\Products\Models;

class Category extends Model implements HasMedia
{
    use HasNestedSet; // kalnoy/nestedset or similar
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'position',
        'is_visible',
        'meta_title',
        'meta_description',
    ];
    
    // Nested Set relationships
    public function parent(): BelongsTo;
    public function children(): HasMany;
    public function ancestors(): Builder;
    public function descendants(): Builder;
    
    // Products
    public function products(): BelongsToMany;
    
    // Include products from all descendants
    public function allProducts(): Builder
    {
        $descendantIds = $this->descendants()->pluck('id');
        return Product::whereHas('categories', function ($q) use ($descendantIds) {
            $q->whereIn('categories.id', $descendantIds->push($this->id));
        });
    }
    
    // Media
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('thumbnail')->singleFile();
        $this->addMediaCollection('banner')->singleFile();
    }
}
```

### Breadcrumb Generation

```php
$category->ancestors()->with('self')->get();
// Returns: [Electronics, Computers, Laptops, Gaming Laptops]

$category->getBreadcrumbs();
// Returns array for UI rendering
```

---

## Collection System

Collections are flexible product groupings that can be:
- **Manual**: Hand-picked products
- **Automatic**: Rule-based dynamic membership

### Collection Model

```php
namespace AIArmada\Products\Models;

class Collection extends Model implements HasMedia
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',           // CollectionType::Manual, Automatic
        'conditions',     // JSON rules for automatic
        'match_type',     // 'all' or 'any' for conditions
        'is_published',
        'published_at',
        'unpublished_at',
        'position',
    ];
    
    protected $casts = [
        'conditions' => 'array',
        'type' => CollectionType::class,
    ];
    
    // Manual collection
    public function products(): BelongsToMany;
    
    // Automatic collection
    public function getComputedProducts(): Builder
    {
        if ($this->type === CollectionType::Manual) {
            return $this->products();
        }
        
        return $this->applyConditions(Product::query());
    }
}
```

### Automatic Collection Conditions

```php
// Example: "Summer Sale" collection
$collection = Collection::create([
    'name' => 'Summer Sale',
    'type' => CollectionType::Automatic,
    'match_type' => 'all',
    'conditions' => [
        ['field' => 'tag', 'operator' => 'equals', 'value' => 'summer'],
        ['field' => 'price', 'operator' => 'less_than', 'value' => 10000],
        ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
    ],
]);

// Example: "New Arrivals" (last 30 days)
$collection = Collection::create([
    'name' => 'New Arrivals',
    'type' => CollectionType::Automatic,
    'conditions' => [
        ['field' => 'created_at', 'operator' => 'greater_than', 'value' => '-30 days'],
    ],
]);
```

### Available Condition Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `equals` | Exact match | `status = active` |
| `not_equals` | Not matching | `status != draft` |
| `contains` | String contains | `name contains "summer"` |
| `starts_with` | String prefix | `sku starts_with "TSH"` |
| `greater_than` | Numeric/date comparison | `price > 5000` |
| `less_than` | Numeric/date comparison | `price < 10000` |
| `in` | Value in list | `category in [1, 2, 3]` |
| `not_in` | Value not in list | `tag not_in ["clearance"]` |

---

## Collection Scheduling

Collections can be time-limited for promotions.

```php
$collection = Collection::create([
    'name' => 'Black Friday Deals',
    'published_at' => Carbon::parse('2025-11-28 00:00:00'),
    'unpublished_at' => Carbon::parse('2025-11-30 23:59:59'),
]);

// Scope
Collection::published(); // Only currently active
Collection::scheduled(); // Upcoming
Collection::expired();   // Past
```

---

## Featured Collections

Special collection types for homepage and navigation.

```php
enum CollectionFeature: string
{
    case Homepage = 'homepage';
    case Navigation = 'navigation';
    case Footer = 'footer';
    case Sidebar = 'sidebar';
}

$collection->features()->attach(CollectionFeature::Homepage);
```

---

## Product Sorting Within Collections

Each collection can have custom product ordering.

```php
// Default sort options
enum CollectionSort: string
{
    case Manual = 'manual';        // Hand-ordered
    case BestSelling = 'best_selling';
    case DateNewest = 'date_newest';
    case DateOldest = 'date_oldest';
    case PriceLowHigh = 'price_asc';
    case PriceHighLow = 'price_desc';
    case TitleAZ = 'title_asc';
    case TitleZA = 'title_desc';
}

$collection->update(['sort_order' => CollectionSort::BestSelling]);
```

---

## SEO Features

### Category SEO

```php
$category->update([
    'meta_title' => 'Gaming Laptops | Best Prices',
    'meta_description' => 'Shop the best gaming laptops...',
    'og_image' => 'path/to/og-image.jpg',
]);
```

### Canonical URLs

```php
// A product in multiple categories has one canonical
$product->update(['primary_category_id' => $category->id]);

$product->getCanonicalUrl();
// /electronics/computers/laptops/macbook-pro
```

---

## Events

| Event | Triggered When |
|-------|----------------|
| `CategoryCreated` | New category added |
| `CategoryMoved` | Category parent changed |
| `CategoryDeleted` | Category removed |
| `CollectionCreated` | New collection created |
| `CollectionUpdated` | Collection modified |
| `CollectionPublished` | Collection goes live |
| `ProductAddedToCollection` | Manual addition |
| `ProductRemovedFromCollection` | Manual removal |

---

## Navigation

**Previous:** [03-variant-system.md](03-variant-system.md)  
**Next:** [05-attributes.md](05-attributes.md)
