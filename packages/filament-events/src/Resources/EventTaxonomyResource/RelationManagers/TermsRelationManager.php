<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTaxonomyResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\Events\Features\Taxonomy\Actions\ManageEventTermAction;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class TermsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'terms';

    protected static ?string $title = 'Terms';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->weight('semibold'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->default('—'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data): EventTerm => app(ManageEventTermAction::class)->save((string) $this->getOwnerRecord()->getKey(), null, $data, Filament::auth()->user()))
                    ->form([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(100)
                            ->unique(modifyRuleUsing: fn ($rule) => $rule->where('event_taxonomy_id', $this->getOwnerRecord()->getKey()), ignoreRecord: true),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3),
                        Select::make('parent_id')
                            ->label('Parent Term')
                            ->relationship(
                                'parent',
                                'name',
                                fn ($query) => $query->where('event_taxonomy_id', $this->getOwnerRecord()->getKey())
                            )
                            ->searchable()
                            ->preload(),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->integer(),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->using(fn (EventTerm $record, array $data): EventTerm => app(ManageEventTermAction::class)->save((string) $this->getOwnerRecord()->getKey(), $record->id, $data, Filament::auth()->user()))
                    ->form([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(100)
                            ->unique(modifyRuleUsing: fn ($rule) => $rule->where('event_taxonomy_id', $this->getOwnerRecord()->getKey()), ignoreRecord: true),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(3),
                        Select::make('parent_id')
                            ->label('Parent Term')
                            ->relationship(
                                'parent',
                                'name',
                                fn ($query) => $query->where('event_taxonomy_id', $this->getOwnerRecord()->getKey())
                            )
                            ->searchable()
                            ->preload(),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->integer(),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
                DeleteAction::make()->using(fn (EventTerm $record): bool => app(ManageEventTermAction::class)->delete((string) $this->getOwnerRecord()->getKey(), $record->id, Filament::auth()->user())),
            ]);
    }
}
