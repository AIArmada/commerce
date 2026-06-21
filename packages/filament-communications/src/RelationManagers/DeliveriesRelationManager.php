<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\RelationManagers;

use AIArmada\Communications\Enums\DeliveryStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Deliveries';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('channel')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('provider'),
                Tables\Columns\TextColumn::make('destination_hint')
                    ->label('Destination'),
                Tables\Columns\TextColumn::make('attempt_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(DeliveryStatus::cases())->pluck('value', 'value')),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
