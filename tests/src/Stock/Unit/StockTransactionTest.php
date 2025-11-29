<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Models\StockTransaction;
use Illuminate\Support\Carbon;

it('has correct fillable attributes', function (): void {
    $transaction = new StockTransaction();
    expect($transaction->getFillable())->each->toBeIn([
        'stockable_type',
        'stockable_id',
        'user_id',
        'quantity',
        'type',
        'reason',
        'note',
        'transaction_date',
    ]);
});

it('getTable uses config', function (): void {
    $transaction = new StockTransaction();
    expect($transaction->getTable())->toBe(config('stock.table_name', 'stock_transactions'));
});

it('morphs to stockable', function (): void {
    $product = Product::create(['name' => 'Test Product']);
    $transaction = StockTransaction::factory()->create([
        'stockable_type' => $product->getMorphClass(),
        'stockable_id' => $product->id,
    ]);
    expect($transaction->stockable->id)->toBe($product->id);
});

it('identifies inbound transaction', function (): void {
    $transaction = StockTransaction::factory()->create(['type' => 'in']);
    expect($transaction->isInbound())->toBeTrue();
    expect($transaction->isOutbound())->toBeFalse();
});

it('identifies outbound transaction', function (): void {
    $transaction = StockTransaction::factory()->create(['type' => 'out']);
    expect($transaction->isInbound())->toBeFalse();
    expect($transaction->isOutbound())->toBeTrue();
});

it('identifies sale reason', function (): void {
    $transaction = StockTransaction::factory()->create(['reason' => 'sale']);
    expect($transaction->isSale())->toBeTrue();
    expect($transaction->isAdjustment())->toBeFalse();
});

it('identifies adjustment reason', function (): void {
    $transaction = StockTransaction::factory()->create(['reason' => 'adjustment']);
    expect($transaction->isSale())->toBeFalse();
    expect($transaction->isAdjustment())->toBeTrue();
});

it('casts attributes correctly', function (): void {
    $transaction = StockTransaction::factory()->create([
        'transaction_date' => now(),
        'quantity' => 10,
    ]);
    expect($transaction->transaction_date)->toBeInstanceOf(Carbon::class);
    expect($transaction->quantity)->toBeInt();
});