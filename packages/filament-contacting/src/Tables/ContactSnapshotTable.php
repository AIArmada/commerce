<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;

final class ContactSnapshotTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('snapshot_type')
                    ->badge()
                    ->label('Type'),

                Tables\Columns\TextColumn::make('reason')
                    ->searchable(),

                Tables\Columns\TextColumn::make('snapshotable_type')
                    ->label('Source Entity'),

                Tables\Columns\TextColumn::make('channel')
                    ->badge(),

                Tables\Columns\TextColumn::make('value')
                    ->limit(50),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('snapshot_type'),

                Tables\Filters\SelectFilter::make('reason'),

                Tables\Filters\SelectFilter::make('channel'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
            ]);
    }
}
