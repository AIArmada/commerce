<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSubLocationResource\Tables;

use AIArmada\Events\Models\EventSubLocation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class EventSubLocationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (EventSubLocation $record): string => $record->slug),

                TextColumn::make('order_column')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('occurrences_count')
                    ->label('Occurrences')
                    ->counts('occurrences')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order_column')
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
