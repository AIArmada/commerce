<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\InvoiceContract;
use AIArmada\Cashier\Drivers\ChipGateway;
use AIArmada\Cashier\Drivers\StripeGateway;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

it('returns an invoice only for the requested gateway customer', function (string $driver, string $name): void {
    config()->set('commerce-support.owner.enabled', false);

    $gateway = Mockery::mock($driver)->makePartial();
    $invoice = Mockery::mock(InvoiceContract::class);
    $invoice->shouldReceive('customerId')->andReturn('customer-a');
    $gateway->shouldReceive('retrieveInvoice')->with('invoice-a')->andReturn($invoice);

    $owner = Mockery::mock(BillableContract::class);
    $owner->shouldReceive('gatewayId')->with($name)->andReturn('customer-a');

    $other = Mockery::mock(BillableContract::class);
    $other->shouldReceive('gatewayId')->with($name)->andReturn('customer-b');

    $unlinked = Mockery::mock(BillableContract::class);
    $unlinked->shouldReceive('gatewayId')->with($name)->andReturn(null);

    expect($gateway->findInvoice($owner, 'invoice-a'))->toBe($invoice)
        ->and($gateway->findInvoice($other, 'invoice-a'))->toBeNull()
        ->and($gateway->findInvoice($unlinked, 'invoice-a'))->toBeNull();
})->with([
    'Stripe' => [StripeGateway::class, 'stripe'],
    'CHIP' => [ChipGateway::class, 'chip'],
]);
