<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTicketTypeResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class EventTicketTypeComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static ?string $title = 'Sub-Ticket Components';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('componentTicketType.name')
                    ->label('Component Ticket'),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric(),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
