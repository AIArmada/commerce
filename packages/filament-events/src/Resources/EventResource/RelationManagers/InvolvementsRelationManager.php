<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class InvolvementsRelationManager extends RelationManager
{
    protected static string $relationship = 'involvements';

    protected static ?string $title = 'People & Roles';

    protected static ?string $recordTitleAttribute = 'role_code';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('involveable_type')->badge(),
                Tables\Columns\TextColumn::make('involveable_id'),
                Tables\Columns\TextColumn::make('role_code')->badge(),
                Tables\Columns\TextColumn::make('prominence')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\IconColumn::make('is_featured')->boolean(),
                Tables\Columns\IconColumn::make('is_primary')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role_code')
                    ->options(['speaker' => 'Speaker', 'organizer' => 'Organizer', 'sponsor' => 'Sponsor', 'moderator' => 'Moderator']),
            ])
            ->headerActions([])
            ->actions([
                ViewAction::make(),
            ]);
    }
}
