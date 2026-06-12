<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class AttendancesRelationManager extends RelationManager
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
                Tables\Columns\TextColumn::make('attendee_type'),
                Tables\Columns\TextColumn::make('attendee_id'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('attendance_type')
                    ->options(['registered' => 'Registered', 'walk_in' => 'Walk-in', 'vip' => 'VIP', 'speaker' => 'Speaker']),
            ])
            ->headerActions([])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }
}
