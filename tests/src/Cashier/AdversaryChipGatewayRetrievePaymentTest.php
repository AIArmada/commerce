<?php

declare(strict_types=1);

use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

/**
 * ADVERSARY PROOF — Attack 3: `ChipGateway::retrievePayment()` (and its twin
 * `retrieveInvoice()`) resolve any purchase id with no ownership check.
 *
 * Contrast: `retrieveSubscription()` on the same class is owner-scoped via
 * `OwnerScopedQuery` (proven by `ChipGatewayOwnerScopeTest`), and the base
 * `AbstractGateway::resolveBillableByGatewayId()` fails closed without an
 * owner — but the `ChipGateway` override resolves through the owner-blind
 * `CashierChip::findBillable()`, and the payment/invoice retrievers skip
 * scoping entirely. Any caller holding a purchase id reads another tenant's
 * money object (amount, customer email, line items).
 *
 * Mock seam: `ChipCollectService` (the gateway-client boundary). The gateway
 * retrieval logic under test is unmocked.
 */
it('refuses to retrieve a payment that belongs to another tenant', function (): void {
    $foreignPurchase = PurchaseData::from([
        'id' => 'purchase-gateway-foreign',
        'created_on' => 1704067200,
        'updated_on' => 1704070800,
        'client' => ['email' => 'other-tenant@example.com'],
        'client_id' => 'chip_cus_foreign_tenant',
        'purchase' => [
            'currency' => 'MYR',
            'total' => 7500,
            'products' => [[
                'name' => 'Other tenant item',
                'price' => 7500,
                'quantity' => 1,
                'discount' => 0,
                'tax_percent' => 0.0,
            ]],
        ],
        'brand_id' => 'brand_123',
        'status' => 'paid',
    ]);

    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldReceive('getPurchase')
        ->once()
        ->with('purchase-gateway-foreign')
        ->andReturn($foreignPurchase);
    app()->instance(ChipCollectService::class, $service);

    $payment = (new ChipGateway([]))->retrievePayment('purchase-gateway-foreign');

    expect($payment)->toBeNull('retrievePayment() returned another tenant purchase with no ownership check.');
});
