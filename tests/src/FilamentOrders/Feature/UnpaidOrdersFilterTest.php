<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use Carbon\CarbonImmutable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

function makeUnpaidFilterTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

function makeUnpaidFilterOrder(string $number, int $grandTotal, ?CarbonImmutable $paidAt): Order
{
    return Order::create([
        'order_number' => $number,
        'status' => $paidAt === null && $grandTotal === 0 ? Processing::class : PendingPayment::class,
        'currency' => 'USD',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
        'paid_at' => $paidAt,
    ]);
}

it('excludes free processing orders from the unpaid filter', function (): void {
    $paid = makeUnpaidFilterOrder('ORD-FILTER-PAID', 10000, CarbonImmutable::now());
    $unpaid = makeUnpaidFilterOrder('ORD-FILTER-UNPAID', 10000, null);
    $free = makeUnpaidFilterOrder('ORD-FILTER-FREE', 0, null);

    $filter = OrderResource::table(makeUnpaidFilterTable())->getFilter('unpaid');

    $ids = $filter->apply(Order::query())->pluck('orders.id')->all();

    expect($ids)->toContain((string) $unpaid->getKey())
        ->not->toContain((string) $paid->getKey())
        ->not->toContain((string) $free->getKey());
});
