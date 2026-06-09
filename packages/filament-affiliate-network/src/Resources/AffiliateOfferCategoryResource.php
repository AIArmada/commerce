<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Pages\CreateAffiliateOfferCategory;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Pages\EditAffiliateOfferCategory;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Pages\ListAffiliateOfferCategories;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Schemas\AffiliateOfferCategoryForm;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Tables\AffiliateOfferCategoriesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class AffiliateOfferCategoryResource extends Resource
{
    protected static ?string $model = AffiliateOfferCategory::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Categories';

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group', 'Affiliate Network');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50) + 2;
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateOfferCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateOfferCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return Builder<AffiliateOfferCategory>
     */
    public static function getEloquentQuery(): Builder
    {
        // Admin resource: bypass owner scope to show all categories network-wide.
        /** @var Builder<AffiliateOfferCategory> $query */
        $query = parent::getEloquentQuery();

        return $query->withoutOwnerScope();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateOfferCategories::route('/'),
            'create' => CreateAffiliateOfferCategory::route('/create'),
            'edit' => EditAffiliateOfferCategory::route('/{record}/edit'),
        ];
    }
}
