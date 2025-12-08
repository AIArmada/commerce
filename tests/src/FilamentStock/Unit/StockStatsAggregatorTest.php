<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentStock\FilamentStockTestCase;
use AIArmada\FilamentStock\Services\StockStatsAggregator;
use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Models\StockTransaction;

uses(FilamentStockTestCase::class);

beforeEach(function (): void {

    // Transactions
    StockTransaction::create([
        'stockable_type' => 'Product',
        'stockable_id' => 'p-1',
        'quantity' => 10,
        'type' => 'in',
        'transaction_date' => now()->subDays(2),
    ]);

    StockTransaction::create([
        'stockable_type' => 'Product',
        'stockable_id' => 'p-1',
        'quantity' => 4,
        'type' => 'out',
        'reason' => 'sale',
        'transaction_date' => now()->subDay(),
    ]);

    StockTransaction::create([
        'stockable_type' => 'Product',
        'stockable_id' => 'p-2',
        'quantity' => 6,
        'type' => 'in',
        'reason' => 'restock',
        'transaction_date' => now()->subHours(6),
    ]);

    // Reservations
    StockReservation::create([
        'stockable_type' => 'Product',
        'stockable_id' => 'p-1',
        'cart_id' => 'cart-active',
        'quantity' => 3,
        'expires_at' => now()->addMinutes(30),
    ]);

    StockReservation::create([
        'stockable_type' => 'Product',
        'stockable_id' => 'p-2',
        'cart_id' => 'cart-expired',
        'quantity' => 2,
        'expires_at' => now()->subMinutes(5),
    ]);
});

it('builds overview stats across transactions and reservations', function (): void {
    $aggregator = app(StockStatsAggregator::class);

    $stats = $aggregator->overview();

    expect($stats['total_transactions'])->toBe(3);
    expect($stats['inbound_transactions'])->toBe(2);
    expect($stats['outbound_transactions'])->toBe(1);
    expect($stats['active_reservations'])->toBe(1);
    expect($stats['expired_reservations'])->toBe(1);
    expect($stats['total_reserved_quantity'])->toBe(3);
});

it('summarizes transaction stats for the recent period', function (): void {
    $aggregator = app(StockStatsAggregator::class);

    $stats = $aggregator->transactionStats(7);

    expect($stats['inbound'])->toBe(16);
    expect($stats['outbound'])->toBe(4);
    expect($stats['net_change'])->toBe(12);
});

it('groups transactions by reason', function (): void {
    $aggregator = app(StockStatsAggregator::class);

    $byReason = $aggregator->transactionsByReason(7);

    expect($byReason)->toMatchArray([
        'sale' => 4,
        'restock' => 6,
        null => 10,
    ]);
});
