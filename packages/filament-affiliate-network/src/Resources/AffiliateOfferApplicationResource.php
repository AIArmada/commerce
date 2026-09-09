<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Pages\ListAffiliateOfferApplications;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Pages\ViewAffiliateOfferApplication;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Schemas\AffiliateOfferApplicationForm;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Schemas\AffiliateOfferApplicationInfolist;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferApplicationResource\Tables\AffiliateOfferApplicationsTable;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class AffiliateOfferApplicationResource extends Resource
{
    protected static ?string $model = AffiliateOfferApplication::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Applications';

    protected static ?string $modelLabel = 'Application';

    protected static ?string $pluralModelLabel = 'Applications';

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
        return config('filament-affiliate-network.navigation.sort', 50) + 3;
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateOfferApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateOfferApplicationsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateOfferApplicationInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return Builder<AffiliateOfferApplication>
     */
    public static function getEloquentQuery(): Builder
    {
        // Admin resource: bypass per-affiliate owner scope to show all applications network-wide.
        /** @var Builder<AffiliateOfferApplication> $query */
        $query = parent::getEloquentQuery()
            ->with([
                'offer' => fn ($builder) => $builder->withoutGlobalScope(ScopesByBelongsToOwner::class),
                'affiliate' => fn ($builder) => $builder->withoutOwnerScope(),
            ]);

        return $query->withoutGlobalScope(ScopesByBelongsToOwner::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateOfferApplications::route('/'),
            'view' => ViewAffiliateOfferApplication::route('/{record}'),
        ];
    }
}
