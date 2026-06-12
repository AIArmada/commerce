<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentPricing\Resources\PromotionResource\Pages;
use AIArmada\FilamentPricing\Resources\PromotionResource\Schemas\PromotionForm;
use AIArmada\FilamentPricing\Resources\PromotionResource\Schemas\PromotionInfolist;
use AIArmada\FilamentPricing\Resources\PromotionResource\Tables\PromotionsTable;
use AIArmada\Promotions\Models\Promotion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Promotion Resource
 *
 * @deprecated Use AIArmada\FilamentPromotions\Resources\PromotionResource instead.
 *             This resource is kept for backward compatibility but delegates to
 *             the canonical filament-promotions package.
 */
final class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-gift';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-pricing.navigation.group');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        if (class_exists(\AIArmada\FilamentPromotions\Resources\PromotionResource::class)) {
            return \AIArmada\FilamentPromotions\Resources\PromotionResource::getNavigationBadge();
        }

        return null;
    }

    /**
     * @return Builder<Promotion>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Promotion> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('promotions.features.owner.enabled', false)) {
            return $query;
        }

        $owner = self::resolveOwner();

        /** @var Builder<Promotion> $scoped */
        $scoped = $query->forOwner(
            $owner,
            (bool) config('promotions.features.owner.include_global', false),
        );

        return $scoped;
    }

    private static function resolveOwner(): ?Model
    {
        if (! (bool) config('promotions.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    public static function form(Schema $schema): Schema
    {
        return PromotionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PromotionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromotionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'view' => Pages\ViewPromotion::route('/{record}'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
