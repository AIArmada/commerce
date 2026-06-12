<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventOccurrenceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class OccurrenceInvolvementsRelationManager extends RelationManager
{
    protected static string $relationship = 'involvements';
    protected static ?string $title = 'People & Roles';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('involveable_type')->badge(),
                Tables\Columns\TextColumn::make('role_code')->badge(),
                Tables\Columns\TextColumn::make('prominence')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role_code')
                    ->options(['speaker' => 'Speaker', 'organizer' => 'Organizer', 'moderator' => 'Moderator']),
            ])
            ->headerActions([])
            ->actions([\Filament\Actions\ViewAction::make()]);
    }
}
