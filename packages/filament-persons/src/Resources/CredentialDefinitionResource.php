<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources;

use AIArmada\FilamentPersons\Resources\CredentialDefinitionResource\Pages\CreateCredentialDefinition;
use AIArmada\FilamentPersons\Resources\CredentialDefinitionResource\Pages\EditCredentialDefinition;
use AIArmada\FilamentPersons\Resources\CredentialDefinitionResource\Pages\ListCredentialDefinitions;
use AIArmada\FilamentPersons\Resources\CredentialDefinitionResource\Pages\ViewCredentialDefinition;
use AIArmada\Persons\Models\CredentialDefinition;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class CredentialDefinitionResource extends Resource
{
    protected static ?string $model = CredentialDefinition::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-persons.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-persons.resources.navigation_sort.credential_definition');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(200),
                        TextInput::make('short_form')
                            ->maxLength(50),
                        TextInput::make('field')
                            ->maxLength(100),
                        Select::make('credential_type')
                            ->options([
                                'academic_degree' => 'Academic Degree',
                                'professional_license' => 'Professional License',
                                'certification' => 'Certification',
                            ])
                            ->required(),
                        TextInput::make('language_code')
                            ->maxLength(10),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('short_form')
                    ->badge(),
                Tables\Columns\TextColumn::make('credential_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('field')
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('credential_type')
                    ->options([
                        'academic_degree' => 'Academic Degree',
                        'professional_license' => 'Professional License',
                        'certification' => 'Certification',
                    ]),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->components([
                        TextEntry::make('name'),
                        TextEntry::make('short_form'),
                        TextEntry::make('credential_type'),
                        TextEntry::make('field'),
                        TextEntry::make('language_code'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCredentialDefinitions::route('/'),
            'create' => CreateCredentialDefinition::route('/create'),
            'view' => ViewCredentialDefinition::route('/{record}'),
            'edit' => EditCredentialDefinition::route('/{record}/edit'),
        ];
    }
}
