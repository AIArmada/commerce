<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages\ListAffiliatePayouts;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages\ViewAffiliatePayout;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\RelationManagers\ConversionsRelationManager;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class AffiliatePayoutResource extends Resource
{
    protected static ?string $model = AffiliatePayout::class;

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AffiliatePayout> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('affiliates.owner.enabled', false)) {
            /** @var Builder<Model> $unscopedQuery */
            $unscopedQuery = $query;

            return $unscopedQuery;
        }

        $scopedQuery = $query->forOwner();

        /** @var Builder<Model> $modelQuery */
        $modelQuery = $scopedQuery;

        return $modelQuery;
    }

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Affiliate Payouts';

    protected static ?string $modelLabel = 'Payout';

    protected static ?string $pluralModelLabel = 'Payouts';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return AffiliatePayoutResource\Tables\AffiliatePayoutsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliatePayoutResource\Schemas\AffiliatePayoutInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            ConversionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliatePayouts::route('/'),
            'view' => ViewAffiliatePayout::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_payouts', 62);
    }
}
