<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages\CreateAffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages\EditAffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Pages\ListAffiliateOffers;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables\AffiliateOffersTable;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class AffiliateOfferResource extends Resource
{
    protected static ?string $model = AffiliateOffer::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Offers';

    protected static ?string $modelLabel = 'Offer';

    protected static ?string $pluralModelLabel = 'Offers';

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
        return config('filament-affiliate-network.navigation.sort', 50) + 1;
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateOfferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateOffersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return Builder<AffiliateOffer>
     */
    public static function getEloquentQuery(): Builder
    {
        // Admin resource: bypass per-site owner scope to show all offers network-wide.
        /** @var Builder<AffiliateOffer> $query */
        $query = parent::getEloquentQuery()
            ->with([
                'site' => fn ($builder) => $builder->withoutOwnerScope(),
                'category' => fn ($builder) => $builder->withoutOwnerScope(),
            ]);

        return $query->withoutGlobalScope(ScopesByBelongsToOwner::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateOffers::route('/'),
            'create' => CreateAffiliateOffer::route('/create'),
            'edit' => EditAffiliateOffer::route('/{record}/edit'),
        ];
    }
}
