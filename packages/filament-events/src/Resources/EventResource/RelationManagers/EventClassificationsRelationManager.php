<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

final class EventClassificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'classifications';

    protected static ?string $title = 'Classifications';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('group_key')
                    ->required()
                    ->maxLength(255),

                TextInput::make('term_key')
                    ->required()
                    ->maxLength(255),

                TextInput::make('term_label')
                    ->maxLength(255),

                TextInput::make('source_type')
                    ->maxLength(255),

                TextInput::make('source_id')
                    ->maxLength(36),

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
            ->recordTitleAttribute('term_label')
            ->columns([
                Tables\Columns\TextColumn::make('group_key')
                    ->label('Group')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('term_key')
                    ->label('Term')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('term_label')
                    ->label('Label')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_column')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
