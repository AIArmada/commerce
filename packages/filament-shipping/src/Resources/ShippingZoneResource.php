<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\Pages;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\RelationManagers;
use Illuminate\Validation\Rule;
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

class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMap;

    protected static string | UnitEnum | null $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 2;

    /**
     * @return Builder<ShippingZone>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ShippingZone> $query */
        $query = parent::getEloquentQuery()->withoutGlobalScope(OwnerScope::class)->withCount('rates');

        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        if ($owner === null) {
            return $query->whereRaw('0 = 1');
        }

        /** @var Builder<ShippingZone> $scoped */
        $scoped = $query->forOwner($owner, includeGlobal: (bool) config('shipping.features.owner.include_global', false));

        return $scoped;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Zone Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->rules(function ($record) {
                                $owner = OwnerContext::resolve();
                                $rule = Rule::unique(ShippingZone::class, 'code')
                                    ->where('owner_type', $owner?->getMorphClass())
                                    ->where('owner_id', $owner?->getKey());

                                if ($record !== null) {
                                    $rule = $rule->ignore($record->id);
                                }

                                return [$rule];
                            })
                            ->maxLength(50),

                        Forms\Components\Select::make('type')
                            ->options([
                                'country' => 'Country',
                                'state' => 'State/Province',
                                'postcode' => 'Postcode Range',
                                'radius' => 'Radius from Point',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority zones are checked first'),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Zone')
                            ->helperText('Fallback for addresses that don\'t match any zone'),

                        Forms\Components\Toggle::make('active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Geographic Conditions')
                    ->schema([
                        Forms\Components\TagsInput::make('countries')
                            ->placeholder('Add country codes (e.g., MYS, SGP)')
                            ->visible(fn (Get $get) => in_array($get('type'), ['country', 'state'])),

                        Forms\Components\TagsInput::make('states')
                            ->placeholder('Add state names')
                            ->visible(fn (Get $get) => $get('type') === 'state'),

                        Forms\Components\Repeater::make('postcode_ranges')
                            ->schema([
                                Forms\Components\TextInput::make('from')
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('to')
                                    ->required()
                                    ->maxLength(20),
                            ])
                            ->columns(2)
                            ->visible(fn (Get $get) => $get('type') === 'postcode'),

                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('center_lat')
                                    ->label('Latitude')
                                    ->numeric(),
                                Forms\Components\TextInput::make('center_lng')
                                    ->label('Longitude')
                                    ->numeric(),
                                Forms\Components\TextInput::make('radius_km')
                                    ->label('Radius (km)')
                                    ->numeric(),
                            ])
                            ->columns(3)
                            ->visible(fn (Get $get) => $get('type') === 'radius'),
                    ])
                    ->visible(fn (Get $get) => $get('type') !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'country' => 'success',
                        'state' => 'info',
                        'postcode' => 'warning',
                        'radius' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->boolean(),

                Tables\Columns\IconColumn::make('active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('rates_count')
                    ->label('Rates')
                    ->counts('rates'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'country' => 'Country',
                        'state' => 'State',
                        'postcode' => 'Postcode',
                        'radius' => 'Radius',
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
            ->defaultSort('priority', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingZones::route('/'),
            'create' => Pages\CreateShippingZone::route('/create'),
            'edit' => Pages\EditShippingZone::route('/{record}/edit'),
        ];
    }
}
