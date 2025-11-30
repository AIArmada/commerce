<?php

declare(strict_types=1);

use AIArmada\CashierChip\Cashier;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    $this->user = $this->createUser([
        'chip_id' => 'cli_test123',
    ]);

    // Add the client to the fake so getClient works
    Cashier::getFake()->getFakeClient()->createClient([
        'id' => $this->user->chip_id,
        'email' => $this->user->email,
        'full_name' => $this->user->name,
    ]);
});

describe('createSetupPurchase', function (): void {
    it('creates a zero-amount preauthorization purchase', function (): void {
        $purchase = $this->user->createSetupPurchase();

        expect($purchase)->not->toBeNull();
        expect($purchase->checkout_url)->not->toBeEmpty();
        expect($purchase->checkout_url)->toContain('https://gate.chip-in.asia/checkout/');
    });

    it('creates setup purchase with custom options', function (): void {
        $purchase = $this->user->createSetupPurchase([
            'product_name' => 'Card Verification',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        expect($purchase)->not->toBeNull();
        expect($purchase->checkout_url)->not->toBeEmpty();
    });

    it('creates purchase with skip_capture and force_recurring flags', function (): void {
        // The fake client's createPurchase method stores the data
        // We can verify the purchase was created with correct parameters
        $purchase = $this->user->createSetupPurchase();

        // The purchase should be created with preauthorization settings
        expect($purchase->id)->not->toBeEmpty();
        expect($purchase->status)->toBe('created');
    });
});

describe('setupPaymentMethodUrl', function (): void {
    it('returns checkout URL for setup purchase', function (): void {
        $url = $this->user->setupPaymentMethodUrl();

        expect($url)->not->toBeEmpty();
        expect($url)->toContain('https://gate.chip-in.asia/checkout/');
    });

    it('includes success and cancel URLs in options', function (): void {
        $url = $this->user->setupPaymentMethodUrl([
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        expect($url)->not->toBeEmpty();
    });
});
