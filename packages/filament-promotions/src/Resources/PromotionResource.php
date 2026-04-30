<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources;

use AIArmada\FilamentPromotions\Models\Promotion;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Pages\CreatePromotion;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Pages\EditPromotion;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Pages\ListPromotions;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Pages\ViewPromotion;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Schemas\PromotionForm;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Schemas\PromotionInfolist;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Tables\PromotionsTable;
use AIArmada\Promotions\Support\PromotionsOwnerScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Promotions';

    protected static ?string $modelLabel = 'Promotion';

    protected static ?string $pluralModelLabel = 'Promotions';

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

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'view' => ViewPromotion::route('/{record}'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = (int) self::getEloquentQuery()
            ->where('is_active', true)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Builder<Promotion>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Promotion> $query */
        $query = parent::getEloquentQuery();

        /** @var Builder<Promotion> $scoped */
        $scoped = PromotionsOwnerScope::applyToOwnedQuery($query);

        return $scoped;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-promotions.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-promotions.resources.navigation_sort.promotions', 10);
    }
}
