<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Pages\ListAffiliateConversions;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Pages\ViewAffiliateConversion;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Schemas\AffiliateConversionForm;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Schemas\AffiliateConversionInfolist;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class AffiliateConversionResource extends Resource
{
    protected static ?string $model = AffiliateConversion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Affiliate Conversions';

    protected static ?string $modelLabel = 'Conversion';

    protected static ?string $pluralModelLabel = 'Conversions';

    public static function form(Schema $schema): Schema
    {
        return AffiliateConversionForm::configure($schema);
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
