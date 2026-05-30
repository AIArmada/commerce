<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PayoutEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_status')
                    ->label('From')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('to_status')
                    ->label('To')
                    ->badge(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No payout events yet');
    }
}
