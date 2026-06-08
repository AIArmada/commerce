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

final class EventAssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Assets';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('role_key')
                    ->required()
                    ->maxLength(255),

                TextInput::make('title')
                    ->maxLength(255),

                TextInput::make('provider')
                    ->maxLength(255),

                TextInput::make('provider_reference')
                    ->maxLength(255),

                TextInput::make('url')
                    ->url()
                    ->columnSpanFull(),

                TextInput::make('alt_text')
                    ->maxLength(255),

                TextInput::make('visibility')
                    ->required()
                    ->maxLength(255)
                    ->default('public')
                    ->helperText('Suggested values: public, private. Hosts may define their own.'),

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

                Tables\Columns\TextColumn::make('role_key')
                    ->label('Role')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('provider')
                    ->label('Provider')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                    ->sortable(),

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
