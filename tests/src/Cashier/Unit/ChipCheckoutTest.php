<?php

declare(strict_types=1);

use AIArmada\Cashier\Gateways\Chip\ChipCheckout;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('ChipCheckout status mapping', function (): void {
    it('keeps overdue purchases unpaid and pending', function (): void {
        $checkout = new ChipCheckout(PurchaseData::from([
            'id' => 'pur_overdue',
            'status' => 'overdue',
        ]));

        expect($checkout->paymentStatus())->toBe('unpaid')
            ->and($checkout->isPending())->toBeTrue()
            ->and($checkout->isExpired())->toBeFalse();
    });

    it('maps a released held purchase to cancelled', function (): void {
        $checkout = new ChipCheckout(PurchaseData::from([
            'id' => 'pur_released',
            'status' => 'released',
        ]));

        expect($checkout->paymentStatus())->toBe('cancelled')
            ->and($checkout->isPending())->toBeFalse();
    });
});
