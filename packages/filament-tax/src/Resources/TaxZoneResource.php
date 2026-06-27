<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources;

use AIArmada\FilamentTax\Resources\TaxZoneResource\Pages;
use AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers;
use AIArmada\FilamentTax\Resources\TaxZoneResource\Schemas\TaxZoneForm;
use AIArmada\FilamentTax\Resources\TaxZoneResource\Tables\TaxZonesTable;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Support\TaxOwnerScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class TaxZoneResource extends Resource
{
    protected static ?string $model = TaxZone::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-tax.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-tax.resources.navigation_sort.zones');

        return is_numeric($sort) ? (int) $sort : null;
    }

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<TaxZone>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = TaxOwnerScope::applyToOwnedQuery(parent::getEloquentQuery());

        /** @phpstan-ignore return.type (template type not preserved through helper) */
        return $query->withCount([
            'rates as rates_count' => fn (Builder $builder) => TaxOwnerScope::applyToOwnedQuery($builder),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return TaxZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxZonesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxZones::route('/'),
            'create' => Pages\CreateTaxZone::route('/create'),
            'view' => Pages\ViewTaxZone::route('/{record}'),
            'edit' => Pages\EditTaxZone::route('/{record}/edit'),
        ];
    }
}
