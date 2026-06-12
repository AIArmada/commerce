<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    protected static ?string $title = 'Reminders';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recipient_type')->badge(),
                Tables\Columns\TextColumn::make('recipient_id'),
                Tables\Columns\TextColumn::make('reminder_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('remind_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['pending' => 'Pending', 'scheduled' => 'Scheduled', 'sent' => 'Sent', 'failed' => 'Failed', 'cancelled' => 'Cancelled']),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
