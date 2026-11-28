<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryBatchResource\Tables;

use AIArmada\FilamentInventory\Actions\DeleteTraceabilityBulkAction;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InventoryBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lot_number')
                    ->label('Lot')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('inventoryable_type')
                    ->label('Product Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('No location')
                    ->sortable(),

                TextColumn::make('quantity_on_hand')
                    ->label('Current')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('available')
                    ->label('Available')
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('CASE WHEN quantity_on_hand > quantity_reserved THEN quantity_on_hand - quantity_reserved ELSE 0 END ' . ($direction === 'desc' ? 'DESC' : 'ASC'))),

                TextColumn::make('quantity_reserved')
                    ->label('Reserved')
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (?InventoryBatch $record): string => match (true) {
                        $record?->expires_at === null => 'gray',
                        $record->isExpired() => 'danger',
                        $record->daysUntilExpiry() <= 7 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InventoryBatch $record): string => $record->getStatusEnum()->color()),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BatchStatus::cases())->mapWithKeys(
                        fn (BatchStatus $status) => [$status->value => $status->label()]
                    )),

                SelectFilter::make('location')
                    ->relationship(
                        name: 'location',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query),
                    ),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteTraceabilityBulkAction::make(),
                ]),
            ]);
    }
}
