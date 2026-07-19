<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use AIArmada\Events\States\OccurrenceStatus\OccurrenceStatus as OccurrenceStatusState;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Contracts\HasColor;
use Filament\Tables;
use Filament\Tables\Table;

final class OccurrencesRelationManager extends RelationManager
{
    protected static string $relationship = 'occurrences';

    protected static ?string $title = 'Occurrences';

    protected static ?string $recordTitleAttribute = 'title';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string | array | null => $state instanceof HasColor ? $state->getColor() : 'gray'),
                Tables\Columns\TextColumn::make('capacity'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(OccurrenceStatusState::options()),
            ])
            ->headerActions([])
            ->actions([
                ViewAction::make(),
            ]);
    }
}
