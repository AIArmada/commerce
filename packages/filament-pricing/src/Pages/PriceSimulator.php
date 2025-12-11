<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Pages;

use AIArmada\Pricing\Services\PricingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;

class PriceSimulator extends Page
{
    public ?array $data = [];

    public ?array $result = null;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected string $view = 'filament-pricing::pages.price-simulator';

    protected static ?string $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Price Simulator';

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Input Parameters')
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
                            ->visible(fn(Forms\Get $get) => $get('product_type') === 'product')
                            ->options(function () {
                                return \AIArmada\Products\Models\Product::query()
                                    ->get()
                                    ->mapWithKeys(fn($p) => [
                                        $p->id => $p->name . ' (Base: RM' . number_format($p->price / 100, 2) . ')',
                                    ]);
                            }),

                        Forms\Components\Select::make('variant_id')
                            ->label('Variant')
                            ->searchable()
                            ->required()
                            ->visible(fn(Forms\Get $get) => $get('product_type') === 'variant')
                            ->options(function () {
                                return \AIArmada\Products\Models\Variant::with('product')
                                    ->get()
                                    ->mapWithKeys(fn($v) => [
                                        $v->id => $v->product->name . ' - ' . $v->sku . ' (RM' . number_format(($v->price ?? $v->product->price) / 100, 2) . ')',
                                    ]);
                            }),

                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->searchable()
                            ->helperText('Optional: simulate for a specific customer')
                            ->options(function () {
                                return \AIArmada\Customers\Models\Customer::query()
                                    ->get()
                                    ->mapWithKeys(fn($c) => [
                                        $c->id => $c->full_name . ' (' . $c->email . ')',
                                    ]);
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
        $data = $this->form->getState();

        // Get the priceable
        $priceable = null;
        if ($data['product_type'] === 'product') {
            $priceable = \AIArmada\Products\Models\Product::find($data['product_id']);
        } else {
            $priceable = \AIArmada\Products\Models\Variant::find($data['variant_id']);
        }

        if (!$priceable) {
            $this->result = null;

            return;
        }

        // Get customer if provided
        $customer = $data['customer_id']
            ? \AIArmada\Customers\Models\Customer::find($data['customer_id'])
            : null;

        // Calculate price using PricingService
        $pricingService = app(PricingService::class);
        $priceResult = $pricingService->calculate(
            priceable: $priceable,
            quantity: (int) $data['quantity'],
            customer: $customer
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
        $this->form->fill();
    }

    public function resultInfolist(Infolist $infolist): Infolist
    {
        if (!$this->result) {
            return $infolist->schema([]);
        }

        return $infolist
            ->state($this->result)
            ->schema([
                Infolists\Components\Section::make('Price Calculation Result')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('original_price')
                                    ->label('Original Price (per unit)')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold),

                                Infolists\Components\TextEntry::make('final_price')
                                    ->label('Final Price (per unit)')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('discount_amount')
                                    ->label('Discount (per unit)')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold)
                                    ->color('danger'),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Quantity')
                                    ->weight(FontWeight::Bold),

                                Infolists\Components\TextEntry::make('total_price')
                                    ->label('Total Price')
                                    ->money('MYR')
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->color('success'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Applied Pricing Rules')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('price_list_name')
                                    ->label('Price List')
                                    ->placeholder('Default pricing')
                                    ->badge()
                                    ->color('info'),

                                Infolists\Components\TextEntry::make('promotion_name')
                                    ->label('Promotion')
                                    ->placeholder('No promotion applied')
                                    ->badge()
                                    ->color('warning'),

                                Infolists\Components\TextEntry::make('tier_description')
                                    ->label('Price Tier')
                                    ->placeholder('No tier pricing')
                                    ->badge()
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('discount_percentage')
                                    ->label('Discount Percentage')
                                    ->placeholder('0%')
                                    ->suffix('%')
                                    ->numeric(decimalPlaces: 2),
                            ]),

                        Infolists\Components\TextEntry::make('discount_source')
                            ->label('Discount Source')
                            ->placeholder('No discount applied')
                            ->columnSpanFull(),
                    ])
                    ->visible(
                        fn() => $this->result['price_list_name'] ||
                        $this->result['promotion_name'] ||
                        $this->result['tier_description'] ||
                        $this->result['discount_source']
                    ),

                Infolists\Components\Section::make('Breakdown')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('breakdown')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('step')
                                    ->label('Step'),
                                Infolists\Components\TextEntry::make('value')
                                    ->label('Value')
                                    ->money('MYR'),
                            ])
                            ->columns(2),
                    ])
                    ->visible(fn() => !empty($this->result['breakdown']))
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('calculate')
                ->label('Calculate Price')
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->action('calculate'),
            \Filament\Actions\Action::make('clear')
                ->label('Clear')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->action('clear')
                ->visible(fn() => $this->result !== null),
        ];
    }
}
