<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources;

use AIArmada\FilamentPricing\Resources\PriceListResource\Pages;
use AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers;
use AIArmada\Pricing\Models\PriceList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Price List Details')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Forms\Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state))
                                    ),

                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'MYR' => 'MYR - Malaysian Ringgit',
                                        'USD' => 'USD - US Dollar',
                                        'SGD' => 'SGD - Singapore Dollar',
                                    ])
                                    ->default('MYR')
                                    ->required(),

                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Scheduling')
                            ->schema([
                                Forms\Components\DateTimePicker::make('starts_at')
                                    ->label('Start Date'),

                                Forms\Components\DateTimePicker::make('ends_at')
                                    ->label('End Date'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Settings')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_default')
                                    ->label('Default Price List')
                                    ->helperText('Used when no other price list applies'),

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher = more priority'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->badge(),

                Tables\Columns\TextColumn::make('prices_count')
                    ->label('Prices')
                    ->counts('prices')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PricesRelationManager::class,
            RelationManagers\TiersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriceLists::route('/'),
            'create' => Pages\CreatePriceList::route('/create'),
            'view' => Pages\ViewPriceList::route('/{record}'),
            'edit' => Pages\EditPriceList::route('/{record}/edit'),
        ];
    }
}
