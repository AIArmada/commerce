# Category & Collection Resources

> **Document:** 03 of 04  
> **Package:** `aiarmada/filament-products`  
> **Status:** Vision

---

## Category Resource

### Tree Structure Management

```php
namespace AIArmada\FilamentProducts\Resources;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Catalog';
}
```

### Category Form

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Section::make()->schema([
            Select::make('parent_id')
                ->label('Parent Category')
                ->relationship('parent', 'name')
                ->searchable()
                ->preload(),
            TextInput::make('name')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($set, $state) => 
                    $set('slug', Str::slug($state))),
            TextInput::make('slug')->required(),
            RichEditor::make('description'),
        ]),

        Section::make('Media')->schema([
            SpatieMediaLibraryFileUpload::make('image')
                ->collection('category')
                ->image(),
            SpatieMediaLibraryFileUpload::make('banner')
                ->collection('banner')
                ->image(),
        ]),

        Section::make('SEO')->schema([
            TextInput::make('meta_title'),
            Textarea::make('meta_description'),
        ])->collapsed(),
    ]);
}
```

### Category Tree Page

```php
class ManageCategoryTree extends Page
{
    protected static string $resource = CategoryResource::class;
    protected static string $view = 'filament-products::pages.category-tree';

    public function getViewData(): array
    {
        return [
            'tree' => Category::tree()->get(),
        ];
    }

    // Drag-and-drop reordering via Livewire
    public function reorder(array $items): void
    {
        Category::rebuildTree($items);
    }
}
```

---

## Collection Resource

### Collection Form with Rule Builder

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Section::make()->schema([
            Select::make('type')
                ->options([
                    'manual' => 'Manual',
                    'automatic' => 'Automatic (Rule-based)',
                ])
                ->required()
                ->live(),
            TextInput::make('name')->required(),
            TextInput::make('slug')->required(),
            RichEditor::make('description'),
        ]),

        // Manual collection - product picker
        Section::make('Products')
            ->schema([
                Select::make('products')
                    ->relationship('products', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ])
            ->visible(fn ($get) => $get('type') === 'manual'),

        // Automatic collection - rule builder
        Section::make('Conditions')
            ->schema([
                Select::make('match_type')
                    ->options([
                        'all' => 'Products must match ALL conditions',
                        'any' => 'Products must match ANY condition',
                    ])
                    ->default('all'),
                Repeater::make('conditions')
                    ->schema([
                        Select::make('field')
                            ->options([
                                'tag' => 'Product Tag',
                                'category' => 'Category',
                                'price' => 'Price',
                                'compare_at_price' => 'Compare At Price',
                                'inventory' => 'Inventory Quantity',
                                'created_at' => 'Created Date',
                            ])
                            ->required()
                            ->live(),
                        Select::make('operator')
                            ->options(fn ($get) => match ($get('field')) {
                                'price', 'compare_at_price', 'inventory' => [
                                    '=' => 'equals',
                                    '>' => 'greater than',
                                    '<' => 'less than',
                                    '>=' => 'at least',
                                    '<=' => 'at most',
                                ],
                                default => [
                                    '=' => 'is',
                                    '!=' => 'is not',
                                    'contains' => 'contains',
                                ],
                            })
                            ->required(),
                        TextInput::make('value')
                            ->required(),
                    ])
                    ->columns(3)
                    ->addActionLabel('Add condition'),
            ])
            ->visible(fn ($get) => $get('type') === 'automatic'),

        Section::make('Scheduling')->schema([
            DateTimePicker::make('published_at'),
            DateTimePicker::make('unpublished_at'),
        ])->columns(2),
    ]);
}
```

### Collection Preview

```php
// Live preview of matching products
public function getMatchingProducts(): Collection
{
    if ($this->type === 'manual') {
        return $this->products;
    }

    return app(CollectionMatcher::class)
        ->match($this->conditions, $this->match_type)
        ->limit(50)
        ->get();
}
```

---

## Navigation

**Previous:** [02-product-resource.md](02-product-resource.md)  
**Next:** [04-dashboard-widgets.md](04-dashboard-widgets.md)
