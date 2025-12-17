<?php

declare(strict_types=1);

use AIArmada\Cashier\Exceptions\GatewayNotFoundException;
use AIArmada\Cashier\Exceptions\InsufficientStockException;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Additional Exceptions', function (): void {
    describe('GatewayNotFoundException', function (): void {
        it('can be created with default message', function (): void {
            $exception = new GatewayNotFoundException;

            expect($exception->getMessage())->toBe('Gateway not found.');
        });

        it('can be created with custom message', function (): void {
            $exception = new GatewayNotFoundException('Custom message', 404);

            expect($exception->getMessage())->toBe('Custom message')
                ->and($exception->getCode())->toBe(404);
        });

        it('can be created with previous exception', function (): void {
            $previous = new Exception('Previous error');
            $exception = new GatewayNotFoundException('Gateway error', 0, $previous);

            expect($exception->getPrevious())->toBe($previous);
        });

        it('can be created for missing gateway', function (): void {
            $exception = GatewayNotFoundException::forGateway('paypal');

            expect($exception->getMessage())->toContain('paypal')
                ->and($exception->getMessage())->toContain('not found');
        });

        it('can be created for missing driver', function (): void {
            $exception = GatewayNotFoundException::forDriver('custom');

            expect($exception->getMessage())->toContain('custom')
                ->and($exception->getMessage())->toContain('driver')
                ->and($exception->getMessage())->toContain('not found');
        });
    });

    describe('InsufficientStockException', function (): void {
        it('can be created with message only', function (): void {
            $exception = new InsufficientStockException('Not enough stock');

            expect($exception->getMessage())->toBe('Not enough stock')
                ->and($exception->getInsufficientItems())->toBe([]);
        });

        it('can be created with insufficient items', function (): void {
            $items = [
                ['sku' => 'SKU001', 'requested' => 5, 'available' => 2],
                ['sku' => 'SKU002', 'requested' => 10, 'available' => 0],
            ];
            $exception = new InsufficientStockException('Insufficient stock for some items', $items);

            expect($exception->getInsufficientItems())->toBe($items)
                ->and($exception->getInsufficientItems())->toHaveCount(2);
        });

        it('returns empty array when no items provided', function (): void {
            $exception = new InsufficientStockException('Out of stock');

            expect($exception->getInsufficientItems())->toBeEmpty();
        });
    });
});
