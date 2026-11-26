<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources;

use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Pages\CreateAffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Pages\EditAffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Pages\ListAffiliateSites;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\RelationManagers\StatementsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Schemas\AffiliateSiteForm;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Tables\AffiliateSitesTable;
use AIArmada\FilamentAffiliateNetwork\Platform\NetworkAdminAccess;
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

    public static function canViewAny(): bool
    {
        return NetworkAdminAccess::allows();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group');
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
        return [
            StatementsRelationManager::class,
        ];
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
