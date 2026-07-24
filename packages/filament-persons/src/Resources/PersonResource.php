<?php

declare(strict_types=1);

namespace AIArmada\FilamentPersons\Resources;

use AIArmada\FilamentPersons\Resources\PersonResource\Pages\CreatePerson;
use AIArmada\FilamentPersons\Resources\PersonResource\Pages\EditPerson;
use AIArmada\FilamentPersons\Resources\PersonResource\Pages\ListPersons;
use AIArmada\FilamentPersons\Resources\PersonResource\Pages\ViewPerson;
use AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers\AffiliationsRelationManager;
use AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers\CredentialAssignmentsRelationManager;
use AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers\NamesRelationManager;
use AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers\TitleAssignmentsRelationManager;
use AIArmada\FilamentPersons\Resources\PersonResource\Schemas\PersonForm;
use AIArmada\FilamentPersons\Resources\PersonResource\Schemas\PersonInfolist;
use AIArmada\FilamentPersons\Resources\PersonResource\Tables\PersonsTable;
use AIArmada\Persons\Models\Person;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-persons.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-persons.resources.navigation_sort.person');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function form(Schema $schema): Schema
    {
        return PersonForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PersonInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PersonsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NamesRelationManager::class,
            TitleAssignmentsRelationManager::class,
            CredentialAssignmentsRelationManager::class,
            AffiliationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersons::route('/'),
            'create' => CreatePerson::route('/create'),
            'view' => ViewPerson::route('/{record}'),
            'edit' => EditPerson::route('/{record}/edit'),
        ];
    }
}
