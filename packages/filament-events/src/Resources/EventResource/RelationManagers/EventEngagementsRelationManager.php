<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use AIArmada\Events\Enums\EventEngagementType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

final class EventEngagementsRelationManager extends RelationManager
{
    protected static string $relationship = 'engagements';

    protected static ?string $title = 'Engagements';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('type')
                    ->options(EventEngagementType::class)
                    ->required(),

                TextInput::make('actor_type')
                    ->maxLength(255),

                TextInput::make('actor_id')
                    ->maxLength(36),

                TextInput::make('occurrence_id')
                    ->label('Occurrence')
                    ->maxLength(36),

                TextInput::make('weight')
                    ->numeric()
                    ->minValue(0)
                    ->default(1),

                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('actor_type')
                    ->label('Actor type')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('actor_id')
                    ->label('Actor ID')
                    ->copyable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('weight')
                    ->label('Weight')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Engaged at')
                    ->dateTime()
                    ->sortable(),
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
