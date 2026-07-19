<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class ClassificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'classifications';

    protected static ?string $title = 'Classifications';

    protected static ?string $recordTitleAttribute = 'taxonomy_code';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('taxonomy.name')
                    ->label('Taxonomy')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('term.name')
                    ->label('Term')
                    ->weight('semibold'),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        Select::make('event_taxonomy_id')
                            ->label('Taxonomy')
                            ->relationship('taxonomy', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('event_term_id')
                            ->label('Term')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->relationship(
                                'term',
                                'name',
                                fn ($query, $get) => $query->where('event_taxonomy_id', $get('event_taxonomy_id'))
                            )
                            ->visible(fn ($get): bool => (bool) $get('event_taxonomy_id')),
                        Toggle::make('is_primary')
                            ->label('Primary'),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->integer(),
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        Select::make('event_taxonomy_id')
                            ->label('Taxonomy')
                            ->relationship('taxonomy', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(),
                        Select::make('event_term_id')
                            ->label('Term')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->relationship('term', 'name'),
                        Toggle::make('is_primary')
                            ->label('Primary'),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->integer(),
                    ]),
                DeleteAction::make(),
            ]);
    }
}
