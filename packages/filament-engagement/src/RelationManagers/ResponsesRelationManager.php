<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class ResponsesRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';
    protected static ?string $title = 'Responses';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('responder_type')->badge(),
                Tables\Columns\TextColumn::make('responder_id'),
                Tables\Columns\TextColumn::make('response_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('responded_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'cancelled' => 'Cancelled']),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
