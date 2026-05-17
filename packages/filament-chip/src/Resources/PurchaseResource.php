<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentChip\Resources\PurchaseResource\Pages\ListPurchases;
use AIArmada\FilamentChip\Resources\PurchaseResource\Pages\ViewPurchase;
use AIArmada\FilamentChip\Resources\PurchaseResource\Schemas\PurchaseInfolist;
use AIArmada\FilamentChip\Resources\PurchaseResource\Tables\PurchaseTable;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

final class PurchaseResource extends BaseChipResource
{
    protected static ?string $model = Purchase::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Purchase';

    protected static ?string $pluralModelLabel = 'Purchases';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('purchase.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('purchase.view');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return PurchaseTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return PurchaseInfolist::configure($schema);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'reference',
            'reference_generated',
            'client->email',
            'client->full_name',
            'purchase->products.name',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'view' => ViewPurchase::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'purchases';
    }
}
