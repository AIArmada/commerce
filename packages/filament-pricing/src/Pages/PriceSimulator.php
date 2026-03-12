<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use BackedEnum;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use UnitEnum;

final class PriceSimulator extends Page
{
    public ?array $data = [];

    public ?array $result = null;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calculator';

    protected string $view = 'filament-pricing::pages.price-simulator';

    protected static string | UnitEnum | null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Price Simulator';

    public function mount(): void
    {
        $this->getSchema('form')?->fill();
    }

    private function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scopeQueryForOwner(string $modelClass, Builder $query, ?Model $owner): Builder
    {
        $model = new $modelClass;

        if ($model instanceof Model && method_exists($model, 'scopeForOwner')) {
            /** @var Builder<TModel> $query */
            $query = $model->scopeForOwner($query, $owner);
        }

        return $query;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Input Parameters')
                    ->schema([
                        Forms\Components\Select::make('product_type')
                            ->label('Product Type')
                            ->options([
                                'product' => 'Product',
                                'variant' => 'Variant',
                            ])
                            ->required()
                            ->live()
                            ->default('product'),

                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->required()
                            ->visible(fn (Get $get) => $get('product_type') === 'product')
                            ->getSearchResultsUsing(function (string $search): array {
                                $owner = $this->resolveOwner();

                                $query = $this->scopeQueryForOwner(
                                    Product::class,
                                    Product::query(),
                                    $owner
                                );

                                return $query
                                    ->where('name', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function (Product $product): array {
                                        $name = (string) $product->getAttribute('name');
                                        $priceMinor = (int) $product->getAttribute('price');

                                        return [
                                            (string) $product->getKey() => $name . ' (Base: RM' . number_format($priceMinor / 100, 2) . ')',
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                if ($value === null) {
                                    return null;
                                }

                                $owner = $this->resolveOwner();

                                $query = $this->scopeQueryForOwner(
                                    Product::class,
                                    Product::query(),
                                    $owner
                                );

                                $product = $query
                                    ->whereKey($value)
                                    ->first();

                                if (! $product instanceof Product) {
                                    return null;
                                }

                                $name = (string) $product->getAttribute('name');
                                $priceMinor = (int) $product->getAttribute('price');

                                return $name . ' (Base: RM' . number_format($priceMinor / 100, 2) . ')';
                            }),

                        Forms\Components\Select::make('variant_id')
                            ->label('Variant')
                            ->searchable()
                            ->required()
                            ->visible(fn (Get $get) => $get('product_type') === 'variant')
                            ->getSearchResultsUsing(function (string $search): array {
                                $owner = $this->resolveOwner();

                                return Variant::query()
                                    ->with('product')
                                    ->where(function ($query) use ($owner, $search): void {
                                        $query->where('sku', 'like', "%{$search}%")
                                            ->orWhereHas('product', function ($inner) use ($owner, $search): void {
                                                $inner = $this->scopeQueryForOwner(
                                                    Product::class,
                                                    $inner,
                                                    $owner
                                                );

                                                $inner
                                                    ->where('name', 'like', "%{$search}%");
                                            });
                                    })
                                    ->whereHas('product', function ($query) use ($owner): void {
                                        $query = $this->scopeQueryForOwner(
                                            Product::class,
                                            $query,
                                            $owner
                                        );
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function (Variant $variant): array {
                                        $productName = (string) $variant->product?->getAttribute('name');
                                        $sku = (string) $variant->getAttribute('sku');

                                        $variantPrice = $variant->getAttribute('price');
                                        $variantPriceMinor = is_int($variantPrice) ? $variantPrice : null;

                                        $productPrice = $variant->product?->getAttribute('price');
                                        $productPriceMinor = is_int($productPrice) ? $productPrice : 0;

                                        $priceMinor = $variantPriceMinor ?? $productPriceMinor;

                                        return [
                                            (string) $variant->getKey() => $productName . ' - ' . $sku . ' (RM' . number_format($priceMinor / 100, 2) . ')',
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                if ($value === null) {
                                    return null;
                                }

                                $owner = $this->resolveOwner();

                                $variant = Variant::query()
                                    ->with('product')
                                    ->whereKey($value)
                                    ->whereHas('product', function ($query) use ($owner): void {
                                        $query = $this->scopeQueryForOwner(
                                            Product::class,
                                            $query,
                                            $owner
                                        );
                                    })
                                    ->first();

                                if (! $variant instanceof Variant) {
                                    return null;
                                }

                                $productName = (string) $variant->product?->getAttribute('name');
                                $sku = (string) $variant->getAttribute('sku');

                                $variantPrice = $variant->getAttribute('price');
                                $variantPriceMinor = is_int($variantPrice) ? $variantPrice : null;

                                $productPrice = $variant->product?->getAttribute('price');
                                $productPriceMinor = is_int($productPrice) ? $productPrice : 0;

                                $priceMinor = $variantPriceMinor ?? $productPriceMinor;

                                return $productName . ' - ' . $sku . ' (RM' . number_format($priceMinor / 100, 2) . ')';
                            }),

                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->searchable()
                            ->helperText('Optional: simulate for a specific customer')
                            ->visible(fn (): bool => class_exists(Customer::class))
                            ->getSearchResultsUsing(function (string $search): array {
                                if (! class_exists(Customer::class)) {
                                    return [];
                                }

                                $owner = $this->resolveOwner();

                                $query = $this->scopeQueryForOwner(Customer::class, Customer::query(), $owner);

                                return $query
                                    ->where(function (Builder $query) use ($search): void {
                                        $query
                                            ->where('full_name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function (Customer $customer): array {
                                        $fullName = (string) $customer->getAttribute('full_name');
                                        $email = (string) $customer->getAttribute('email');

                                        return [
                                            (string) $customer->getKey() => $fullName . ' (' . $email . ')',
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                if ($value === null || ! class_exists(Customer::class)) {
                                    return null;
                                }

                                $owner = $this->resolveOwner();

                                $query = $this->scopeQueryForOwner(Customer::class, Customer::query(), $owner);

                                $customer = $query
                                    ->whereKey($value)
                                    ->first();

                                if (! $customer instanceof Customer) {
                                    return null;
                                }

                                $fullName = (string) $customer->getAttribute('full_name');
                                $email = (string) $customer->getAttribute('email');

                                return $fullName . ' (' . $email . ')';
                            }),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1),

                        Forms\Components\DateTimePicker::make('effective_date')
                            ->label('Effective Date')
                            ->default(now())
                            ->native(false)
                            ->helperText('Simulate pricing at a specific date/time'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function calculate(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->data ?? [];

        if ($data === []) {
            $this->result = null;

            return;
        }

        $owner = $this->resolveOwner();

        // Get the priceable
        $priceable = null;
        if ($data['product_type'] === 'product') {
            $query = $this->scopeQueryForOwner(
                Product::class,
                Product::query(),
                $owner
            );

            $priceable = $query->find($data['product_id']);
        } else {
            $priceable = Variant::query()
                ->whereHas('product', function ($query) use ($owner): void {
                    $query = $this->scopeQueryForOwner(
                        Product::class,
                        $query,
                        $owner
                    );
                })
                ->find($data['variant_id']);
        }

        if (! $priceable) {
            $this->result = null;

            return;
        }

        // Get customer if provided
        $customer = null;

        $customerId = Arr::get($data, 'customer_id');
        if (is_string($customerId) && $customerId !== '' && class_exists(Customer::class)) {
            $query = $this->scopeQueryForOwner(Customer::class, Customer::query(), $owner);
            $customer = $query->find($customerId);
        }

        // Calculate price using PriceCalculator
        $pricingService = app(PriceCalculatorInterface::class);
        $context = $customer ? ['customer_id' => (string) $customer->getKey()] : [];

        $effectiveAt = Arr::get($data, 'effective_date');

        if ($effectiveAt instanceof DateTimeInterface || (is_string($effectiveAt) && $effectiveAt !== '')) {
            $context['effective_at'] = $effectiveAt;
        }
        /** @var Priceable $priceable */
        $priceResult = $pricingService->calculate(
            item: $priceable,
            quantity: (int) $data['quantity'],
            context: $context
        );

        $this->result = [
            'original_price' => $priceResult->originalPrice,
            'final_price' => $priceResult->finalPrice,
            'discount_amount' => $priceResult->discountAmount,
            'discount_source' => $priceResult->discountSource,
            'discount_percentage' => $priceResult->discountPercentage,
            'price_list_name' => $priceResult->priceListName,
            'tier_description' => $priceResult->tierDescription,
            'promotion_name' => $priceResult->promotionName,
            'breakdown' => $priceResult->breakdown,
            'quantity' => (int) $data['quantity'],
            'unit_price' => $priceResult->finalPrice,
            'total_price' => $priceResult->finalPrice * (int) $data['quantity'],
        ];
    }

    public function clear(): void
    {
        $this->result = null;
        $this->getSchema('form')?->fill();
    }

    public function resultInfolist(Schema $schema): Schema
    {
        if (! $this->result) {
            return $schema->schema([]);
        }

        return $schema
            ->state($this->result)
            ->schema([
                Section::make('Price Calculation Result')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('original_price')
                                    ->label('Original Price (per unit)')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('final_price')
                                    ->label('Final Price (per unit)')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),

                                TextEntry::make('discount_amount')
                                    ->label('Discount (per unit)')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold)
                                    ->color('danger'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('quantity')
                                    ->label('Quantity')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('total_price')
                                    ->label('Total Price')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large)
                                    ->color('success'),
                            ]),
                    ]),

                Section::make('Applied Pricing Rules')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('price_list_name')
                                    ->label('Price List')
                                    ->placeholder('Default pricing')
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('promotion_name')
                                    ->label('Promotion')
                                    ->placeholder('No promotion applied')
                                    ->badge()
                                    ->color('warning'),

                                TextEntry::make('tier_description')
                                    ->label('Price Tier')
                                    ->placeholder('No tier pricing')
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('discount_percentage')
                                    ->label('Discount Percentage')
                                    ->placeholder('0%')
                                    ->suffix('%')
                                    ->numeric(decimalPlaces: 2),
                            ]),

                        TextEntry::make('discount_source')
                            ->label('Discount Source')
                            ->placeholder('No discount applied')
                            ->columnSpanFull(),
                    ])
                    ->visible(
                        fn () => $this->result['price_list_name'] ||
                        $this->result['promotion_name'] ||
                        $this->result['tier_description'] ||
                        $this->result['discount_source']
                    ),

                Section::make('Breakdown')
                    ->schema([
                        RepeatableEntry::make('breakdown')
                            ->label('')
                            ->schema([
                                TextEntry::make('step')
                                    ->label('Step'),
                                TextEntry::make('value')
                                    ->label('Value')
                                    ->money('MYR'),
                            ])
                            ->columns(2),
                    ])
                    ->visible(fn () => ! empty($this->result['breakdown']))
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calculate')
                ->label('Calculate Price')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->action('calculate'),
            Action::make('clear')
                ->label('Clear')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->action('clear')
                ->visible(fn () => $this->result !== null),
        ];
    }
}
