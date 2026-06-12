<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class ReactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'reactions';

    protected static ?string $title = 'Reactions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reactor_type')->badge(),
                Tables\Columns\TextColumn::make('reactor_id'),
                Tables\Columns\TextColumn::make('reaction_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('reacted_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'removed' => 'Removed']),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
