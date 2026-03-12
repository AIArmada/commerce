<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers;

use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Price Lists';

    protected static ?string $recordTitleAttribute = 'price_list.name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('price_list_id')
                    ->label('Price List')
                    ->relationship('priceList', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('amount')
                    ->label('Price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->suffix(fn ($get) => $get('currency') ?? 'MYR')
                    ->helperText('Enter amount in smallest currency unit (cents/sen)'),

                TextInput::make('compare_amount')
                    ->label('Compare at Price')
                    ->numeric()
                    ->minValue(0)
                    ->suffix(fn ($get) => $get('currency') ?? 'MYR')
                    ->helperText('Original price before discount'),

                TextInput::make('currency')
                    ->label('Currency')
                    ->default('MYR')
                    ->required()
                    ->maxLength(3),

                TextInput::make('min_quantity')
                    ->label('Minimum Quantity')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),

                DateTimePicker::make('starts_at')
                    ->label('Start Date')
                    ->helperText('When this price becomes active'),

                DateTimePicker::make('ends_at')
                    ->label('End Date')
                    ->helperText('When this price expires')
                    ->after('starts_at'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('price_list.name')
            ->modifyQueryUsing(
                fn (Builder $query) => $query
                    ->join('price_lists', 'prices.price_list_id', '=', 'price_lists.id')
                    ->select('prices.*')
                    ->orderBy('price_lists.priority', 'asc')
                    ->orderBy('prices.id', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('priceList.name')
                    ->label('Price List')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Price')
                    ->money(fn (Price $record): string => $record->currency, divideBy: 100)
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('compare_amount')
                    ->label('Compare At')
                    ->money(fn (Price $record): string => $record->currency, divideBy: 100)
                    ->placeholder('—')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('discount')
                    ->label('Discount')
                    ->badge()
                    ->color('success')
                    ->state(function (Price $record): string {
                        if (! $record->hasDiscount()) {
                            return '—';
                        }

                        return $record->getDiscountPercentage() . '% off';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('min_quantity')
                    ->label('Min Qty')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn (Price $record): bool => $record->isActive())
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('price_list_id')
                    ->label('Price List')
                    ->relationship('priceList', 'name')
                    ->preload(),

                Tables\Filters\TernaryFilter::make('has_discount')
                    ->label('Has Discount')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('compare_amount')
                            ->whereColumn('compare_amount', '>', 'amount'),
                        false: fn (Builder $query) => $query->whereNull('compare_amount')
                            ->orWhereColumn('compare_amount', '<=', 'amount'),
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['priceable_type'] = $this->getOwnerRecord()->getMorphClass();
                        $data['priceable_id'] = $this->getOwnerRecord()->getKey();

                        return $data;
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Price Details')
                    ->schema([
                        TextEntry::make('priceList.name')
                            ->label('Price List'),

                        TextEntry::make('amount')
                            ->label('Price')
                            ->money(fn (Price $record): string => $record->currency, divideBy: 100),

                        TextEntry::make('compare_amount')
                            ->label('Compare at Price')
                            ->money(fn (Price $record): string => $record->currency, divideBy: 100)
                            ->placeholder('—'),

                        TextEntry::make('discount_percentage')
                            ->label('Discount')
                            ->badge()
                            ->color('success')
                            ->formatStateUsing(function (Price $record): string {
                                if (! $record->hasDiscount()) {
                                    return '—';
                                }

                                return $record->getDiscountPercentage() . '% off';
                            }),

                        TextEntry::make('min_quantity')
                            ->label('Minimum Quantity'),

                        TextEntry::make('starts_at')
                            ->label('Starts At')
                            ->dateTime('d M Y, H:i')
                            ->placeholder('—'),

                        TextEntry::make('ends_at')
                            ->label('Ends At')
                            ->dateTime('d M Y, H:i')
                            ->placeholder('—'),

                        TextEntry::make('is_active')
                            ->label('Currently Active')
                            ->badge()
                            ->formatStateUsing(fn (Price $record): string => $record->isActive() ? 'Yes' : 'No')
                            ->color(fn (Price $record): string => $record->isActive() ? 'success' : 'gray'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Only show this relation manager if the pricing package is installed
        return class_exists(Price::class) && class_exists(PriceList::class);
    }
}
