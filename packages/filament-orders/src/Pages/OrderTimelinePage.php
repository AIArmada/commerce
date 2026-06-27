<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Pages;

use AIArmada\FilamentOrders\Resources\OrderResource;
use BackedEnum;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use UnitEnum;

class OrderTimelinePage extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'Order Timeline';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-orders.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-orders.pages.navigation_sort.timeline');
    }

    public static function getNavigationLabel(): string
    {
        return 'Order Timeline';
    }

    public function table(Table $table): Table
    {
        return OrderResource::table($table)
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
