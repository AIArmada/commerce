<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\Tables;

use Filament\Actions;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PriceListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('currency')
                    ->label('Currency')
                    ->badge(),

                TextColumn::make('prices_count')
                    ->label('Prices')
                    ->counts('prices')
                    ->alignEnd(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                TextColumn::make('deactivated_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === null ? 'Active' : 'Deactivated')
                    ->color(fn ($state) => $state === null ? 'success' : 'danger'),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                TernaryFilter::make('deactivated_at')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Deactivated')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('deactivated_at'),
                        false: fn (Builder $query) => $query->whereNotNull('deactivated_at'),
                    ),

                TernaryFilter::make('is_default')
                    ->label('Default'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
