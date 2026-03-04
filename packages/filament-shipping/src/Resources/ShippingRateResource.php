<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentShipping\Resources\ShippingRateResource\Pages;
use AIArmada\Shipping\Models\ShippingRate;
use AIArmada\Shipping\Models\ShippingZone;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ShippingRateResource extends Resource
{
    protected static ?string $model = ShippingRate::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string | UnitEnum | null $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Shipping Rates';

    /**
     * @return Builder<ShippingRate>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ShippingRate> $query */
        $query = parent::getEloquentQuery()->with('zone');

        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        if ($owner === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas('zone', /** @phpstan-ignore-next-line */ function (Builder $q) use ($owner): void {
            $q->forOwner($owner, includeGlobal: true); // @phpstan-ignore method.notFound
        });
    }

    public static function form(Schema $schema): Schema
    {
        $zoneOptions = static::getZoneOptions();

        return $schema
            ->schema([
                Section::make('Rate Details')
                    ->schema([
                        Forms\Components\Select::make('zone_id')
                            ->label('Shipping Zone')
                            ->options($zoneOptions)
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('method_code')
                            ->required()
                            ->maxLength(50),

                        Forms\Components\Select::make('carrier_code')
                            ->options([
                                'jnt' => 'J&T Express',
                                'flat_rate' => 'Flat Rate',
                                'manual' => 'Manual',
                            ])
                            ->placeholder('All carriers'),

                        Forms\Components\Select::make('calculation_type')
                            ->options([
                                'flat' => 'Flat Rate',
                                'per_kg' => 'Per Kilogram',
                                'per_item' => 'Per Item',
                                'percentage' => 'Percentage of Order',
                                'table' => 'Table Based',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\Toggle::make('active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('base_rate')
                            ->numeric()
                            ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                            ->required()
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                        Forms\Components\TextInput::make('per_unit_rate')
                            ->numeric()
                            ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0)
                            ->visible(fn (Get $get) => in_array($get('calculation_type'), ['per_kg', 'per_item', 'percentage'])),

                        Forms\Components\TextInput::make('min_charge')
                            ->numeric()
                            ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : null),

                        Forms\Components\TextInput::make('max_charge')
                            ->numeric()
                            ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : null),

                        Forms\Components\TextInput::make('free_shipping_threshold')
                            ->numeric()
                            ->prefix(fn (): string => currency_symbol(config('shipping.defaults.currency', 'MYR')))
                            ->helperText('Orders above this amount get free shipping')
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : null),

                        Forms\Components\Repeater::make('rate_table')
                            ->schema([
                                Forms\Components\TextInput::make('min_weight')
                                    ->numeric()
                                    ->suffix('g')
                                    ->required(),
                                Forms\Components\TextInput::make('max_weight')
                                    ->numeric()
                                    ->suffix('g')
                                    ->required(),
                                Forms\Components\TextInput::make('rate')
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
                                Forms\Components\TextInput::make('estimated_days_min')
                                    ->label('Min Days')
                                    ->numeric(),
                                Forms\Components\TextInput::make('estimated_days_max')
                                    ->label('Max Days')
                                    ->numeric(),
                            ])
                            ->columns(2),

                        Forms\Components\Textarea::make('description')
                            ->rows(2),
                    ]),

                Section::make('Conditions')
                    ->description('Optional rules that determine when this rate applies')
                    ->schema([
                        Forms\Components\Repeater::make('conditions')
                            ->schema([
                                Forms\Components\Select::make('type')
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

                                Forms\Components\TextInput::make('value')
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
                                'min_order_total' => 'Min Order: RM' . number_format(($state['value'] ?? 0) / 100, 2),
                                'max_order_total' => 'Max Order: RM' . number_format(($state['value'] ?? 0) / 100, 2),
                                'min_items' => 'Min Items: ' . ($state['value'] ?? '?'),
                                'max_items' => 'Max Items: ' . ($state['value'] ?? '?'),
                                default => null,
                            }),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Zone')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('method_code')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('carrier_code')
                    ->badge()
                    ->color('info')
                    ->placeholder('All'),

                Tables\Columns\TextColumn::make('calculation_type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'flat' => 'success',
                        'per_kg' => 'info',
                        'per_item' => 'warning',
                        'percentage' => 'primary',
                        'table' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('base_rate')
                    ->formatStateUsing(fn (ShippingRate $record): string => $record->formatted_base_rate)
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_estimate')
                    ->label('Delivery')
                    ->getStateUsing(fn (ShippingRate $record) => $record->getDeliveryEstimate())
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->relationship('zone', 'name'),

                Tables\Filters\SelectFilter::make('calculation_type')
                    ->options([
                        'flat' => 'Flat Rate',
                        'per_kg' => 'Per Kilogram',
                        'per_item' => 'Per Item',
                        'percentage' => 'Percentage',
                        'table' => 'Table Based',
                    ]),

                Tables\Filters\SelectFilter::make('carrier_code')
                    ->options([
                        'jnt' => 'J&T Express',
                        'flat_rate' => 'Flat Rate',
                        'manual' => 'Manual',
                    ]),

                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('zone.name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingRates::route('/'),
            'create' => Pages\CreateShippingRate::route('/create'),
            'edit' => Pages\EditShippingRate::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function getZoneOptions(): array
    {
        $query = ShippingZone::query()->where('active', true);

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner !== null) {
                $query->forOwner($owner, includeGlobal: true);
            }
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }
}
