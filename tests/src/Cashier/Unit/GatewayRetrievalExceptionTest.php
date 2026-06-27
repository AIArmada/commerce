<?php

declare(strict_types=1);

use AIArmada\Cashier\Exceptions\GatewayRetrievalException;
use AIArmada\Cashier\Gateways\ChipGateway;
use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

uses(CashierTestCase::class);

it('returns null for stripe not-found retrievals', function (): void {
    $gateway = new class extends StripeGateway
    {
        public function client(): StripeClient
        {
            throw InvalidRequestException::factory('No such payment_intent', 404);
        }
    };

    expect($gateway->retrievePayment('pi_missing'))->toBeNull();
});

it('throws for unexpected stripe retrieval failures', function (): void {
    $gateway = new class extends StripeGateway
    {
        public function client(): StripeClient
        {
            throw new RuntimeException('Stripe transport failed');
        }
    };

    expect(fn (): mixed => $gateway->retrievePayment('pi_transport'))
        ->toThrow(GatewayRetrievalException::class);
});

it('throws for unexpected chip retrieval failures', function (): void {
    $gateway = new class extends ChipGateway
    {
        public function client(): ChipCollectService
        {
            throw new RuntimeException('CHIP transport failed');
        }
    };

    expect(fn (): mixed => $gateway->retrievePayment('purchase_transport'))
        ->toThrow(GatewayRetrievalException::class);
});
