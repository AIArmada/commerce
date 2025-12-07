<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShipmentResource\RelationManagers;

use AIArmada\Shipping\Enums\TrackingStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $recordTitleAttribute = 'description';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Date/Time')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('normalized_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (TrackingStatus $state) => $state->getColor())
                    ->icon(fn (TrackingStatus $state) => $state->getIcon()),

                Tables\Columns\TextColumn::make('description')
                    ->wrap(),

                Tables\Columns\TextColumn::make('location')
                    ->formatStateUsing(fn ($record) => $record->getFormattedLocation()),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('occurred_at', 'desc');
    }
}
