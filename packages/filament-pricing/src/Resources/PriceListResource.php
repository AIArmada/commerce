<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentPricing\Resources\PriceListResource\Pages;
use AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers;
use AIArmada\FilamentPricing\Resources\PriceListResource\Schemas\PriceListForm;
use AIArmada\FilamentPricing\Resources\PriceListResource\Schemas\PriceListInfolist;
use AIArmada\FilamentPricing\Resources\PriceListResource\Tables\PriceListsTable;
use AIArmada\Pricing\Models\PriceList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-pricing.navigation.group');
    }

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<PriceList>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<PriceList> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('pricing.features.owner.enabled', false)) {
            return $query;
        }

        $owner = self::resolveOwner();

        /** @var Builder<PriceList> $scoped */
        $scoped = $query->forOwner(
            $owner,
            (bool) config('pricing.features.owner.include_global', false),
        );

        return $scoped;
    }

    private static function resolveOwner(): ?Model
    {
        if (! (bool) config('pricing.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    public static function form(Schema $schema): Schema
    {
        return PriceListForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PriceListInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        if (! class_exists('\\AIArmada\\Products\\Models\\Product') || ! class_exists('\\AIArmada\\Products\\Models\\Variant')) {
            return [];
        }

        return [
            RelationManagers\PricesRelationManager::class,
            RelationManagers\TiersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriceLists::route('/'),
            'create' => Pages\CreatePriceList::route('/create'),
            'view' => Pages\ViewPriceList::route('/{record}'),
            'edit' => Pages\EditPriceList::route('/{record}/edit'),
        ];
    }
}
