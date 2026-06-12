<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class OccurrenceTicketTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketTypes';
    protected static ?string $title = 'Ticket Types';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('code')->badge(),
                Tables\Columns\TextColumn::make('access_type')->badge(),
                Tables\Columns\TextColumn::make('price'),
                Tables\Columns\TextColumn::make('quota'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('access_type')
                    ->options(['entry' => 'Entry', 'seating' => 'Seating', 'standing' => 'Standing']),
            ])
            ->headerActions([])
            ->actions([\Filament\Actions\ViewAction::make()]);
    }
}
