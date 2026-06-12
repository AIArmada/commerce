<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSessionResource\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class SessionAttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Attendances';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attendance_type')->badge(),
                Tables\Columns\TextColumn::make('checked_in_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('check_in_source')->badge(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([ViewAction::make()]);
    }
}
