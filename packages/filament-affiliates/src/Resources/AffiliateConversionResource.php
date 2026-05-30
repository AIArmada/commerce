<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Pages\ListAffiliateConversions;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Pages\ViewAffiliateConversion;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Schemas\AffiliateConversionInfolist;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use AIArmada\FilamentAffiliates\Support\OwnerScopedQuery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateConversionResource extends Resource
{
    protected static ?string $model = AffiliateConversion::class;

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AffiliateConversion> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('affiliates.owner.enabled', false)) {
            /** @var Builder<Model> $unscopedQuery */
            $unscopedQuery = $query;

            return $unscopedQuery;
        }

        $scopedQuery = OwnerScopedQuery::throughAffiliate($query);

        /** @var Builder<Model> $modelQuery */
        $modelQuery = $scopedQuery;

        return $modelQuery;
    }

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Affiliate Conversions';

    protected static ?string $modelLabel = 'Conversion';

    protected static ?string $pluralModelLabel = 'Conversions';

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAnyAbility([
            'affiliate_conversion.viewAny',
            'affiliate_conversion.update',
            'affiliate.approve',
        ]);
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAnyAbility([
            'affiliate_conversion.view',
            'affiliate_conversion.update',
            'affiliate.approve',
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function table(Table $table): Table
    {
        return AffiliateConversionsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateConversionInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateConversions::route('/'),
            'view' => ViewAffiliateConversion::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_conversions', 61);
    }
}
