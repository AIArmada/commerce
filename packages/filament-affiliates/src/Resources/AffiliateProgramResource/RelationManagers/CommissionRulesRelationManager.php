<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CommissionRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionRules';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Select::make('rule_type')
                ->options(CommissionRuleType::class)
                ->required(),

            TextInput::make('priority')
                ->numeric()
                ->required()
                ->default(0),

            Select::make('commission_type')
                ->options(CommissionType::class)
                ->required(),

            TextInput::make('commission_value')
                ->label('Commission Value')
                ->numeric()
                ->required(),

            DateTimePicker::make('starts_at'),

            DateTimePicker::make('ends_at'),

            Toggle::make('is_active')
                ->default(true),

            KeyValue::make('conditions')
                ->keyLabel('Condition')
                ->valueLabel('Value')
                ->columnSpanFull(),

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

                TextColumn::make('rule_type')
                    ->badge(),

                TextColumn::make('priority')
                    ->sortable(),

                TextColumn::make('commission_type')
                    ->badge(),

                TextColumn::make('commission_value')
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->dateTime()
                    ->placeholder('—'),
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
