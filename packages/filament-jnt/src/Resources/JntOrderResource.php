<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources;

use AIArmada\FilamentJnt\Resources\JntOrderResource\Pages\ListJntOrders;
use AIArmada\FilamentJnt\Resources\JntOrderResource\Pages\ViewJntOrder;
use AIArmada\FilamentJnt\Resources\JntOrderResource\Schemas\JntOrderInfolist;
use AIArmada\FilamentJnt\Resources\JntOrderResource\Tables\JntOrderTable;
use AIArmada\Jnt\Models\JntOrder;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

final class JntOrderResource extends BaseJntResource
{
    protected static ?string $model = JntOrder::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'Shipping Order';

    protected static ?string $pluralModelLabel = 'Shipping Orders';

    protected static ?string $recordTitleAttribute = 'order_id';

    #[Override]
    public static function table(Table $table): Table
    {
        return JntOrderTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return JntOrderInfolist::configure($schema);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'order_id',
            'tracking_number',
            'customer_code',
            'last_status',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJntOrders::route('/'),
            'view' => ViewJntOrder::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'orders';
    }
}
