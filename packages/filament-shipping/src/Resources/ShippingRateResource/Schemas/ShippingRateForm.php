<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingRateResource\Schemas;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Shipping\Models\ShippingZone;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class ShippingRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Rate Details')
                    ->schema([
                        Select::make('zone_id')
                            ->label('Shipping Zone')
                            ->options(fn () => self::getZoneOptions())
                            ->required()
                            ->searchable(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('method_code')
                            ->required()
                            ->maxLength(50),

                        Select::make('carrier_code')
                            ->options([
                                'jnt' => 'J&T Express',
                                'flat_rate' => 'Flat Rate',
                                'manual' => 'Manual',
                            ])
                            ->placeholder('All carriers'),

                        Select::make('calculation_type')
                            ->options([
                                'flat' => 'Flat Rate',
                                'per_kg' => 'Per Kilogram',
                                'per_item' => 'Per Item',
                                'percentage' => 'Percentage of Order',
                                'table' => 'Table Based',
                            ])
                            ->required()
                            ->live(),

                        Toggle::make('active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Pricing')
                    ->schema([
                        TextInput::make('base_rate')
                            ->numeric()
                            ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                            ->required()
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                        TextInput::make('per_unit_rate')
                            ->numeric()
                            ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0)
                            ->visible(fn (Get $get) => in_array($get('calculation_type'), ['per_kg', 'per_item', 'percentage'])),

                        TextInput::make('min_charge')
                            ->numeric()
                            ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : null),

                        TextInput::make('max_charge')
                            ->numeric()
                            ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : null),

                        TextInput::make('free_shipping_threshold')
                            ->numeric()
                            ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                            ->helperText('Orders above this amount get free shipping')
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : null),

                        Repeater::make('rate_table')
                            ->schema([
                                TextInput::make('min_weight')
                                    ->numeric()
                                    ->suffix('g')
                                    ->required(),
                                TextInput::make('max_weight')
                                    ->numeric()
                                    ->suffix('g')
                                    ->required(),
                                TextInput::make('rate')
                                    ->numeric()
                                    ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                                    ->required()
                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),
                            ])
                            ->columns(3)
                            ->visible(fn (Get $get) => $get('calculation_type') === 'table'),
                    ])
                    ->columns(2),

                Section::make('Delivery Estimate')
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('estimated_days_min')
                                    ->label('Min Days')
                                    ->numeric(),
                                TextInput::make('estimated_days_max')
                                    ->label('Max Days')
                                    ->numeric(),
                            ])
                            ->columns(2),

                        Textarea::make('description')
                            ->rows(2),
                    ]),

                Section::make('Conditions')
                    ->description('Optional rules that determine when this rate applies')
                    ->schema([
                        Repeater::make('conditions')
                            ->schema([
                                Select::make('type')
                                    ->options([
                                        'min_weight' => 'Minimum Weight (g)',
                                        'max_weight' => 'Maximum Weight (g)',
                                        'min_order_total' => 'Minimum Order Total',
                                        'max_order_total' => 'Maximum Order Total',
                                        'min_items' => 'Minimum Items',
                                        'max_items' => 'Maximum Items',
                                    ])
                                    ->required()
                                    ->live(),

                                TextInput::make('value')
                                    ->numeric()
                                    ->required()
                                    ->prefix(fn (Get $get): ?string => in_array($get('type'), ['min_order_total', 'max_order_total'])
                                        ? currency_symbol(config('shipping.defaults.currency', 'MYR'))
                                        : null)
                                    ->suffix(fn (Get $get): ?string => match ($get('type')) {
                                        'min_weight', 'max_weight' => 'g',
                                        default => null,
                                    })
                                    ->formatStateUsing(fn ($state, Get $get) => in_array($get('type'), ['min_order_total', 'max_order_total']) && $state
                                        ? $state / 100
                                        : $state)
                                    ->dehydrateStateUsing(fn ($state, Get $get) => in_array($get('type'), ['min_order_total', 'max_order_total']) && $state
                                        ? (int) ($state * 100)
                                        : (int) $state),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add condition')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => match ($state['type'] ?? null) {
                                'min_weight' => 'Min Weight: ' . ($state['value'] ?? '?') . 'g',
                                'max_weight' => 'Max Weight: ' . ($state['value'] ?? '?') . 'g',
                                'min_order_total' => 'Min Order: ' . MoneyFormatter::formatMinor((int) ($state['value'] ?? 0), config('shipping.defaults.currency', 'MYR')),
                                'max_order_total' => 'Max Order: ' . MoneyFormatter::formatMinor((int) ($state['value'] ?? 0), config('shipping.defaults.currency', 'MYR')),
                                'min_items' => 'Min Items: ' . ($state['value'] ?? '?'),
                                'max_items' => 'Max Items: ' . ($state['value'] ?? '?'),
                                default => null,
                            }),
                    ])
                    ->collapsed(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function getZoneOptions(): array
    {
        $query = ShippingZone::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->where('active', true);

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                $query->whereRaw('0 = 1');
            } else {
                $query->forOwner($owner, includeGlobal: (bool) config('shipping.features.owner.include_global', false));
            }
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }
}
