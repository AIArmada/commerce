<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\RelationManagers;

use AIArmada\Communications\Enums\CommunicationEventSource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class CommunicationTimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Timeline';

    protected static ?string $recordTitleAttribute = 'event';

    protected static ?string $inverseRelationship = 'communication';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event')
                    ->searchable(),
                Tables\Columns\TextColumn::make('source')
                    ->badge(),
                Tables\Columns\TextColumn::make('provider'),
                Tables\Columns\TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('received_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->options(collect(CommunicationEventSource::cases())->pluck('value', 'value')),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
