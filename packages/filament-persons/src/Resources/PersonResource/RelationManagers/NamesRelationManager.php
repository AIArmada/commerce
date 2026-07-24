<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class NamesRelationManager extends RelationManager
{
    protected static string $relationship = 'names';

    protected static ?string $title = 'Names';

    protected static ?string $recordTitleAttribute = 'full_name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('language_code')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        Select::make('name_type')
                            ->options([
                                'legal' => 'Legal',
                                'display' => 'Display',
                                'birth' => 'Birth',
                                'religious' => 'Religious',
                                'professional' => 'Professional',
                                'previous' => 'Previous',
                            ])
                            ->required(),
                        TextInput::make('full_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('language_code')
                            ->required()
                            ->maxLength(10)
                            ->default('en'),
                        Checkbox::make('is_primary'),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
