<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\Product;
use AIArmada\Stock\Console\CleanupExpiredReservationsCommand;
use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Services\StockService;
use Illuminate\Console\Command;

describe('CleanupExpiredReservationsCommand', function (): void {
    beforeEach(function (): void {
        $this->stockService = app(StockService::class);
        $this->product = Product::create(['name' => 'Test Product']);
        $this->stockService->addStock($this->product, 100);
    });

    it('has correct command signature', function (): void {
        $this->artisan('stock:cleanup-reservations --help')
            ->assertSuccessful();
    });

    it('cleans up expired reservations', function (): void {
        // Create active reservation
        StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'active-cart',
            'quantity' => 10,
            'expires_at' => now()->addMinutes(30),
        ]);

        // Create expired reservations
        StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'expired-1',
            'quantity' => 20,
            'expires_at' => now()->subMinutes(10),
        ]);

        StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'expired-2',
            'quantity' => 15,
            'expires_at' => now()->subMinutes(5),
        ]);

        expect(StockReservation::count())->toBe(3);

        $this->artisan('stock:cleanup-reservations')
            ->assertSuccessful();

        expect(StockReservation::count())->toBe(1);
        expect(StockReservation::first()->cart_id)->toBe('active-cart');
    });

    it('outputs correct count of cleaned reservations', function (): void {
        // Create expired reservations
        StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'expired-1',
            'quantity' => 20,
            'expires_at' => now()->subMinutes(10),
        ]);

        StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'expired-2',
            'quantity' => 15,
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->artisan('stock:cleanup-reservations')
            ->expectsOutputToContain('2')
            ->assertSuccessful();
    });

    it('handles case with no expired reservations', function (): void {
        // Create only active reservation
        StockReservation::create([
            'stockable_type' => $this->product->getMorphClass(),
            'stockable_id' => $this->product->id,
            'cart_id' => 'active-cart',
            'quantity' => 10,
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->artisan('stock:cleanup-reservations')
            ->assertSuccessful();

        expect(StockReservation::count())->toBe(1);
    });

    it('can be scheduled', function (): void {
        // Verify the command is schedulable
        $command = new CleanupExpiredReservationsCommand;

        expect($command)->toBeInstanceOf(Command::class);
    });
});
