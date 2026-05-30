<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CreativesRelationManager extends RelationManager
{
    protected static string $relationship = 'creatives';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('type')
                ->required()
                ->maxLength(100),

            TextInput::make('asset_url')
                ->required()
                ->url(),

            TextInput::make('destination_url')
                ->required()
                ->url(),

            TextInput::make('tracking_code')
                ->required()
                ->maxLength(255),

            TextInput::make('width')
                ->numeric(),

            TextInput::make('height')
                ->numeric(),

            KeyValue::make('metadata')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('asset_url')
                    ->label('Asset')
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                    ->limit(40),

                TextColumn::make('destination_url')
                    ->label('Destination')
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                    ->limit(40),

                TextColumn::make('tracking_code')
                    ->label('Tracking')
                    ->copyable(),
            ])
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
