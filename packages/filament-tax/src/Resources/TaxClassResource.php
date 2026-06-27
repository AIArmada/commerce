<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources;

use AIArmada\FilamentTax\Resources\TaxClassResource\Pages;
use AIArmada\FilamentTax\Resources\TaxClassResource\Schemas\TaxClassForm;
use AIArmada\FilamentTax\Resources\TaxClassResource\Tables\TaxClassesTable;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Support\TaxOwnerScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class TaxClassResource extends Resource
{
    protected static ?string $model = TaxClass::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-tax.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-tax.resources.navigation_sort.classes');

        return is_numeric($sort) ? (int) $sort : null;
    }

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<TaxClass>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @phpstan-ignore return.type (template type not preserved through helper) */
        return TaxOwnerScope::applyToOwnedQuery(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return TaxClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxClassesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxClasses::route('/'),
            'create' => Pages\CreateTaxClass::route('/create'),
            'edit' => Pages\EditTaxClass::route('/{record}/edit'),
        ];
    }
}
