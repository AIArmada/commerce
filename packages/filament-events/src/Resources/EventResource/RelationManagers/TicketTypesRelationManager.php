<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class TicketTypesRelationManager extends RelationManager
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
                Tables\Columns\TextColumn::make('price')->money(fn ($record) => $record->currency ?? 'MYR'),
                Tables\Columns\TextColumn::make('quota'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('access_type')
                    ->options(['entry' => 'Entry', 'seating' => 'Seating', 'standing' => 'Standing', 'package' => 'Package']),
            ])
            ->headerActions([])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }
}
