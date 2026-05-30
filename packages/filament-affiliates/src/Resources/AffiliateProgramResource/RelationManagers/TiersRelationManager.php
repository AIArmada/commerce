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

final class TiersRelationManager extends RelationManager
{
    protected static string $relationship = 'tiers';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('level')
                ->numeric()
                ->required(),

            TextInput::make('commission_rate_basis_points')
                ->label('Commission Rate (basis points)')
                ->numeric()
                ->required(),

            TextInput::make('min_conversions')
                ->numeric()
                ->required()
                ->default(0),

            TextInput::make('min_revenue')
                ->numeric()
                ->required()
                ->default(0),

            KeyValue::make('benefits')
                ->keyLabel('Benefit')
                ->valueLabel('Description')
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

                TextColumn::make('level')
                    ->sortable(),

                TextColumn::make('commission_rate_basis_points')
                    ->label('Commission')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : ($state / 100) . '%')
                    ->sortable(),

                TextColumn::make('min_conversions')
                    ->label('Min Conversions')
                    ->sortable(),

                TextColumn::make('min_revenue')
                    ->label('Min Revenue')
                    ->money('USD')
                    ->sortable(),
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
