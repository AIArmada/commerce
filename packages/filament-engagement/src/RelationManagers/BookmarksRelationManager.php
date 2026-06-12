<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class BookmarksRelationManager extends RelationManager
{
    protected static string $relationship = 'bookmarks';
    protected static ?string $title = 'Bookmarks';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bookmarker_type')->badge(),
                Tables\Columns\TextColumn::make('bookmarker_id'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('bookmarked_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'archived' => 'Archived']),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
