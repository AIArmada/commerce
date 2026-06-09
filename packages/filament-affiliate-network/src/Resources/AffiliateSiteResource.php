<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages\CreateAffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages\EditAffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages\ListAffiliateSites;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Schemas\AffiliateSiteForm;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Tables\AffiliateSitesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class AffiliateSiteResource extends Resource
{
    protected static ?string $model = AffiliateSite::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Sites';

    protected static ?string $modelLabel = 'Site';

    protected static ?string $pluralModelLabel = 'Sites';

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group', 'Affiliate Network');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50);
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateSiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateSitesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return Builder<AffiliateSite>
     */
    public static function getEloquentQuery(): Builder
    {
        // Admin resource: bypass owner scope to show all sites network-wide.
        /** @var Builder<AffiliateSite> $query */
        $query = parent::getEloquentQuery();

        return $query->withoutOwnerScope();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateSites::route('/'),
            'create' => CreateAffiliateSite::route('/create'),
            'edit' => EditAffiliateSite::route('/{record}/edit'),
        ];
    }
}
