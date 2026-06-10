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

final class EventChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'changeNotices';

    protected static ?string $title = 'Change Notices';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('change_key')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Built-in keys: schedule_changed, location_changed, speaker_changed, title_changed, topic_changed, people_changed, organizer_changed, content_changed, cancelled, postponed, replacement_linked, other. Hosts may define their own keys.'),

                TextInput::make('severity')
                    ->maxLength(255)
                    ->default('info')
                    ->helperText('Suggested values: info, high, urgent.'),

                TextInput::make('state')
                    ->required()
                    ->maxLength(255)
                    ->default('draft')
                    ->helperText('Suggested values: draft, published, retracted.'),

                TextInput::make('replacement_event_id')
                    ->label('Replacement event')
                    ->maxLength(36),

                TextInput::make('replacement_occurrence_id')
                    ->label('Replacement occurrence')
                    ->maxLength(36),

                DateTimePicker::make('published_at'),

                DateTimePicker::make('retracted_at'),

                KeyValue::make('changed_sections')
                    ->columnSpanFull(),

                KeyValue::make('before_snapshot')
                    ->label('Before')
                    ->columnSpanFull(),

                KeyValue::make('after_snapshot')
                    ->label('After')
                    ->columnSpanFull(),

                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('change_key')
            ->columns([
                Tables\Columns\TextColumn::make('change_key')
                    ->label('Change')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('state')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'retracted' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
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
