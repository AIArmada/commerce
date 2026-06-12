<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class FollowersRelationManager extends RelationManager
{
    protected static string $relationship = 'follows';

    protected static ?string $title = 'Followers';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('follower_type')->badge(),
                Tables\Columns\TextColumn::make('follower_id'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('followed_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'muted' => 'Muted', 'unfollowed' => 'Unfollowed']),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
