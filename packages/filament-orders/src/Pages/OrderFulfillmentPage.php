<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Pages;

use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\Orders\States\Processing;
use BackedEnum;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use UnitEnum;

class OrderFulfillmentPage extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected static string | UnitEnum | null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Fulfillment';

    public static function getNavigationLabel(): string
    {
        return 'Fulfillment';
    }

    public function table(Table $table): Table
    {
        return OrderResource::table($table)
            ->modifyQueryUsing(fn ($query) => $query->whereState('status', Processing::class))
            ->defaultSort('created_at', 'asc');
    }
}
