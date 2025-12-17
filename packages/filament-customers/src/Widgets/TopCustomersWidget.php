<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Widgets;

use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopCustomersWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Customers by LTV';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CustomersOwnerScope::applyToOwnedQuery(Customer::query())
                    ->where('lifetime_value', '>', 0)
                    ->orderByDesc('lifetime_value')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Customer')
                    ->description(fn ($record) => $record->email),

                Tables\Columns\TextColumn::make('lifetime_value')
                    ->label('Lifetime Value')
                    ->money('MYR', divideBy: 100)
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->numeric()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('average_order')
                    ->label('AOV')
                    ->getStateUsing(fn ($record) => $record->getAverageOrderValue())
                    ->money('MYR', divideBy: 100)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('last_order_at')
                    ->label('Last Order')
                    ->dateTime('d M Y')
                    ->placeholder('Never'),
            ])
            ->paginated(false);
    }
}
