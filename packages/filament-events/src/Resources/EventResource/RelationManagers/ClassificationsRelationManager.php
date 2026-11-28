<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\Events\Features\Taxonomy\Actions\ManageEventClassificationAction;
use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class ClassificationsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

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
                    ->using(fn (array $data): EventClassification => app(ManageEventClassificationAction::class)->save((string) $this->getOwnerRecord()->getKey(), null, $data, Filament::auth()->user()))
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
                    ->using(fn (EventClassification $record, array $data): EventClassification => app(ManageEventClassificationAction::class)->save((string) $this->getOwnerRecord()->getKey(), $record->id, $data, Filament::auth()->user()))
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
                            ->relationship('term', 'name', fn ($query, $get) => $query->where('event_taxonomy_id', $get('event_taxonomy_id'))),
                        Toggle::make('is_primary')
                            ->label('Primary'),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->integer(),
                    ]),
                DeleteAction::make()->using(fn (EventClassification $record): bool => app(ManageEventClassificationAction::class)->delete((string) $this->getOwnerRecord()->getKey(), $record->id, Filament::auth()->user())),
            ]);
    }
}
