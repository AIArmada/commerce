<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use AIArmada\Events\Models\Event;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

final class EventAgendaItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'agendaItems';

    protected static ?string $title = 'Agenda';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('occurrence_id')
                    ->label('Occurrence')
                    ->options(function (): array {
                        $event = $this->getOwnerRecord();

                        if (! $event instanceof Event) {
                            return [];
                        }

                        return $event->occurrences()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->required()
                    ->helperText('Agenda items belong to a specific occurrence. Pick one from this event.'),

                TextInput::make('segment_key')
                    ->required()
                    ->maxLength(255),

                TextInput::make('segment_type')
                    ->maxLength(255),

                TextInput::make('title')
                    ->maxLength(255),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                DateTimePicker::make('starts_at'),

                DateTimePicker::make('ends_at'),

                TextInput::make('duration_minutes')
                    ->numeric()
                    ->minValue(0),

                TextInput::make('order_column')
                    ->numeric()
                    ->minValue(0),

                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Untitled'),

                Tables\Columns\TextColumn::make('segment_key')
                    ->label('Segment')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('occurrence.name')
                    ->label('Occurrence')
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration (min)')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('order_column')
                    ->label('Order')
                    ->sortable(),
            ])
            ->defaultSort('order_column')
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
