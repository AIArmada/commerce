<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources;

use AIArmada\FilamentTax\Resources\TaxZoneResource\Pages;
use AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers;
use AIArmada\Tax\Models\TaxZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxZoneResource extends Resource
{
    protected static ?string $model = TaxZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Zone Details')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Zone Name')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->maxLength(20)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Unique identifier (e.g., MY, MY-SEL)'),

                                Forms\Components\Select::make('type')
                                    ->label('Zone Type')
                                    ->options([
                                        'country' => 'Country',
                                        'state' => 'State/Region',
                                        'postcode' => 'Postcode Range',
                                    ])
                                    ->default('country')
                                    ->required(),

                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Geographic Matching')
                            ->schema([
                                Forms\Components\TagsInput::make('countries')
                                    ->label('Countries')
                                    ->helperText('ISO country codes (MY, SG, etc.)')
                                    ->placeholder('Add country code'),

                                Forms\Components\TagsInput::make('states')
                                    ->label('States/Regions')
                                    ->helperText('State names or codes')
                                    ->placeholder('Add state'),

                                Forms\Components\TagsInput::make('postcodes')
                                    ->label('Postcodes')
                                    ->helperText('Exact postcodes, ranges (10000-19999), or wildcards (50*)')
                                    ->placeholder('Add postcode pattern'),
                            ])
                            ->columns(3),
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
                                    ->label('Default Zone')
                                    ->helperText('Used when no other zone matches'),

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher = checked first'),
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
                    ->label('Zone')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->code),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('countries')
                    ->label('Countries')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),

                Tables\Columns\TextColumn::make('rates_count')
                    ->label('Rates')
                    ->counts('rates')
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
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'country' => 'Country',
                        'state' => 'State',
                        'postcode' => 'Postcode',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
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
            RelationManagers\RatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxZones::route('/'),
            'create' => Pages\CreateTaxZone::route('/create'),
            'view' => Pages\ViewTaxZone::route('/{record}'),
            'edit' => Pages\EditTaxZone::route('/{record}/edit'),
        ];
    }
}
