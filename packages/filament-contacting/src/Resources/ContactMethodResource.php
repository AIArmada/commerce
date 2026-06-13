<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources;

use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\FilamentContacting\Schemas\ContactMethodFormSchema;
use AIArmada\FilamentContacting\Schemas\ContactMethodInfolistSchema;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use AIArmada\FilamentContacting\Tables\ContactMethodTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;

final class ContactMethodResource extends Resource
{
    protected static ?string $model = ContactMethod::class;

    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-phone';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-contacting.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-contacting.navigation.icons.contact_methods', parent::getNavigationIcon());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        return ContactMethodTable::table($table);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(ContactMethodFormSchema::make());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema(ContactMethodInfolistSchema::make());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        $guard = app(GuardsContactingUi::class);
        $pages = [
            'index' => ContactMethodResource\Pages\ListContactMethods::route('/'),
            'view' => ContactMethodResource\Pages\ViewContactMethod::route('/{record}'),
        ];

        if (! $guard->contactMethodsReadOnly()) {
            $pages['create'] = ContactMethodResource\Pages\CreateContactMethod::route('/create');
            $pages['edit'] = ContactMethodResource\Pages\EditContactMethod::route('/{record}/edit');
        }

        return $pages;
    }
}
