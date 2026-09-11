<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers;

use AIArmada\Events\Models\EventLocation;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class OccurrenceLocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';

    protected static ?string $title = 'Locations';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('location_role')->badge(),
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->state(fn (EventLocation $record): ?string => $record->getPrimaryAddressData()?->city),
                Tables\Columns\TextColumn::make('state')
                    ->state(fn (EventLocation $record): ?string => $record->getPrimaryAddressData()?->state),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([ViewAction::make()]);
    }
}
