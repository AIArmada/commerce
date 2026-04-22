<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;

use function Pest\Laravel\mock;

it('resolves cashier payment status through the active gateway', function (): void {
    $payment = mock(PaymentContract::class);
    $payment->shouldReceive('isSucceeded')->once()->andReturn(true);
    $payment->shouldReceive('id')->once()->andReturn('pay_123');
    $payment->shouldReceive('redirectUrl')->once()->andReturn(null);
    $payment->shouldReceive('rawAmount')->once()->andReturn(2500);
    $payment->shouldReceive('currency')->once()->andReturn('MYR');
    $payment->shouldReceive('status')->once()->andReturn('succeeded');
    $payment->shouldReceive('toArray')->once()->andReturn(['status' => 'succeeded']);

    $gateway = mock(GatewayContract::class);
    $gateway->shouldReceive('findPayment')->once()->with('pay_123')->andReturn($payment);

    $gatewayManager = mock(GatewayManager::class);
    $gatewayManager->shouldReceive('gateway')->once()->andReturn($gateway);

    app()->instance(GatewayManager::class, $gatewayManager);

    $result = app(CashierProcessor::class)->checkStatus('pay_123');

    expect($result->status)->toBe(PaymentStatus::Completed)
        ->and($result->paymentId)->toBe('pay_123')
        ->and($result->amount)->toBe(2500)
        ->and($result->currency)->toBe('MYR');
});