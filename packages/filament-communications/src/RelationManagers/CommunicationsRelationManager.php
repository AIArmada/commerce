<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\RelationManagers;

use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationStatus;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class CommunicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'communications';

    protected static ?string $title = 'Communications';

    protected static ?string $recordTitleAttribute = 'purpose';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('purpose')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('direction')
                    ->badge(),
                Tables\Columns\TextColumn::make('category')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(CommunicationStatus::cases())->pluck('value', 'value')),
                Tables\Filters\SelectFilter::make('category')
                    ->options(collect(CommunicationCategory::cases())->pluck('value', 'value')),
                Tables\Filters\SelectFilter::make('direction')
                    ->options(collect(CommunicationDirection::cases())->pluck('value', 'value')),
            ])
            ->headerActions([])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }
}
