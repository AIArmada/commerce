<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

final class EventAttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    protected static ?string $title = 'Attendance';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('occurrence_id')
                    ->label('Occurrence')
                    ->maxLength(36),

                TextInput::make('registration_id')
                    ->label('Registration')
                    ->maxLength(36),

                TextInput::make('attendee_type')
                    ->maxLength(255),

                TextInput::make('attendee_id')
                    ->maxLength(36),

                TextInput::make('recorded_by_type')
                    ->maxLength(255),

                TextInput::make('recorded_by_id')
                    ->maxLength(36),

                TextInput::make('source')
                    ->required()
                    ->maxLength(255)
                    ->default('registration')
                    ->helperText('Suggested values: registration, walk_in, manual. Hosts may define their own.'),

                TextInput::make('status')
                    ->required()
                    ->maxLength(255)
                    ->default('present')
                    ->helperText('Suggested values: present, absent, late, left_early. Hosts may define their own.'),

                DateTimePicker::make('checked_in_at'),

                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'present' => 'success',
                        'absent' => 'danger',
                        'late' => 'warning',
                        'left_early' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('attendee_type')
                    ->label('Attendee type')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('attendee_id')
                    ->label('Attendee')
                    ->copyable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Checked in')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
