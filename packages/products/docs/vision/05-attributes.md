# Product Attributes System

> **Document:** 05 of 08  
> **Package:** `aiarmada/products`  
> **Status:** Vision

---

## Overview

The Attributes system enables dynamic, flexible product data beyond fixed database columns. Attributes support multiple input types, validation rules, and can be used for filtering, search, and comparison.

---

## Attribute Types

| Type | Input | Storage | Use Case |
|------|-------|---------|----------|
| `text` | TextInput | VARCHAR | Brand, Material |
| `textarea` | Textarea | TEXT | Care instructions |
| `number` | NumberInput | DECIMAL | Weight, Dimensions |
| `boolean` | Toggle | BOOLEAN | Is Organic, Is Handmade |
| `select` | Select | VARCHAR | Country of Origin |
| `multiselect` | CheckboxList | JSON | Features, Tags |
| `date` | DatePicker | DATE | Harvest Date |
| `color` | ColorPicker | VARCHAR(7) | Display Color |
| `media` | FileUpload | Spatie Media | Spec Sheets |

---

## Attribute Model

```php
namespace AIArmada\Products\Models;

class Attribute extends Model
{
    protected $fillable = [
        'code',                 // Unique identifier (e.g., 'material')
        'name',                 // Display name (e.g., 'Material')
        'type',                 // AttributeType enum
        'validation',           // JSON validation rules
        'options',              // JSON options for select/multiselect
        'is_required',
        'is_filterable',        // Show in filter sidebar
        'is_searchable',        // Include in search index
        'is_comparable',        // Show in product comparison
        'is_visible_on_front',  // Show on product page
        'position',             // Sort order
    ];

    protected $casts = [
        'type' => AttributeType::class,
        'validation' => 'array',
        'options' => 'array',
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
        'is_searchable' => 'boolean',
        'is_comparable' => 'boolean',
        'is_visible_on_front' => 'boolean',
    ];

    // Relationships
    public function groups(): BelongsToMany;
    public function values(): HasMany;
}
```

---

## Attribute Group

Organize attributes into logical groups for admin UI.

```php
namespace AIArmada\Products\Models;

class AttributeGroup extends Model
{
    protected $fillable = [
        'name',
        'code',
        'position',
    ];

    public function attributes(): BelongsToMany;
}
```

Example groups:
- **General**: Brand, Material, Color
- **Dimensions**: Weight, Height, Width, Depth
- **Specifications**: Power, Voltage, Compatibility
- **Care**: Washing Instructions, Storage

---

## Attribute Value Model

```php
namespace AIArmada\Products\Models;

class AttributeValue extends Model
{
    protected $fillable = [
        'attribute_id',
        'attributable_type',    // Product, Variant
        'attributable_id',
        'value',                // The actual value
        'locale',               // For translatable attributes
    ];

    public function attribute(): BelongsTo;
    public function attributable(): MorphTo;

    // Cast value based on attribute type
    public function getTypedValueAttribute()
    {
        return $this->attribute->castValue($this->value);
    }
}
```

---

## Product Integration

```php
class Product extends Model
{
    use HasAttributes;

    // Get attribute value
    public function getAttribute(string $code): mixed
    {
        return $this->attributeValues()
            ->whereHas('attribute', fn ($q) => $q->where('code', $code))
            ->first()
            ?->typed_value;
    }

    // Set attribute value
    public function setAttribute(string $code, mixed $value): void
    {
        $attribute = Attribute::where('code', $code)->firstOrFail();

        $this->attributeValues()->updateOrCreate(
            ['attribute_id' => $attribute->id],
            ['value' => $attribute->serializeValue($value)]
        );
    }

    // Get all attributes as array
    public function getAttributes(): array
    {
        return $this->attributeValues()
            ->with('attribute')
            ->get()
            ->mapWithKeys(fn ($v) => [$v->attribute->code => $v->typed_value])
            ->toArray();
    }
}
```

---

## Dynamic Filament Form

```php
class ProductResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            // Fixed fields
            TextInput::make('name'),
            TextInput::make('sku'),
            
            // Dynamic attribute groups
            ...self::getAttributeSchema(),
        ]);
    }

    protected static function getAttributeSchema(): array
    {
        return AttributeGroup::with('attributes')
            ->ordered()
            ->get()
            ->map(function (AttributeGroup $group) {
                return Section::make($group->name)
                    ->schema(
                        $group->attributes->map(fn ($attr) => 
                            self::buildAttributeField($attr)
                        )->toArray()
                    );
            })
            ->toArray();
    }

    protected static function buildAttributeField(Attribute $attribute): Component
    {
        $field = match ($attribute->type) {
            AttributeType::Text => TextInput::make("attributes.{$attribute->code}"),
            AttributeType::Textarea => Textarea::make("attributes.{$attribute->code}"),
            AttributeType::Number => TextInput::make("attributes.{$attribute->code}")->numeric(),
            AttributeType::Boolean => Toggle::make("attributes.{$attribute->code}"),
            AttributeType::Select => Select::make("attributes.{$attribute->code}")
                ->options($attribute->options),
            AttributeType::Multiselect => CheckboxList::make("attributes.{$attribute->code}")
                ->options($attribute->options),
            AttributeType::Date => DatePicker::make("attributes.{$attribute->code}"),
            AttributeType::Color => ColorPicker::make("attributes.{$attribute->code}"),
        };

        return $field
            ->label($attribute->name)
            ->required($attribute->is_required);
    }
}
```

---

## Filtering by Attributes

```php
class ProductFilter
{
    public function apply(Builder $query, array $filters): Builder
    {
        foreach ($filters as $code => $value) {
            $attribute = Attribute::where('code', $code)
                ->where('is_filterable', true)
                ->first();

            if (!$attribute) {
                continue;
            }

            $query->whereHas('attributeValues', function ($q) use ($attribute, $value) {
                $q->where('attribute_id', $attribute->id);

                if (is_array($value)) {
                    $q->whereIn('value', $value);
                } else {
                    $q->where('value', $value);
                }
            });
        }

        return $query;
    }
}
```

---

## Predefined Attribute Sets

Group attributes into sets for different product types:

```php
// AttributeSet model
class AttributeSet extends Model
{
    protected $fillable = ['name', 'code'];

    public function attributes(): BelongsToMany;
}

// Usage
$clothingSet = AttributeSet::where('code', 'clothing')->first();
// Includes: Size, Color, Material, Care Instructions

$electronicsSet = AttributeSet::where('code', 'electronics')->first();
// Includes: Voltage, Power, Warranty, Compatibility
```

---

## Navigation

**Previous:** [04-categories-collections.md](04-categories-collections.md)  
**Next:** [06-integration.md](06-integration.md)
