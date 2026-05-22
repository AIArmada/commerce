<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use Mockery\Expectation;

use function Pest\Laravel\mock;

it('resolves cashier payment status through the active gateway', function (): void {
    $payment = mock(PaymentContract::class);
    /** @var Expectation $isSucceeded */
    $isSucceeded = $payment->shouldReceive('isSucceeded');
    $isSucceeded->once()->andReturn(true);
    /** @var Expectation $id */
    $id = $payment->shouldReceive('id');
    $id->once()->andReturn('pay_123');
    /** @var Expectation $redirectUrl */
    $redirectUrl = $payment->shouldReceive('redirectUrl');
    $redirectUrl->once()->andReturn(null);
    /** @var Expectation $rawAmount */
    $rawAmount = $payment->shouldReceive('rawAmount');
    $rawAmount->once()->andReturn(2500);
    /** @var Expectation $currency */
    $currency = $payment->shouldReceive('currency');
    $currency->once()->andReturn('MYR');
    /** @var Expectation $paymentStatus */
    $paymentStatus = $payment->shouldReceive('status');
    $paymentStatus->once()->andReturn('succeeded');
    /** @var Expectation $toArray */
    $toArray = $payment->shouldReceive('toArray');
    $toArray->once()->andReturn(['status' => 'succeeded']);

    $gateway = mock(GatewayContract::class);
    /** @var Expectation $findPayment */
    $findPayment = $gateway->shouldReceive('findPayment');
    $findPayment->once()->with('pay_123')->andReturn($payment);

    $gatewayManager = mock(GatewayManager::class);
    /** @var Expectation $gatewayExpectation */
    $gatewayExpectation = $gatewayManager->shouldReceive('gateway');
    $gatewayExpectation->once()->andReturn($gateway);

    app()->instance(GatewayManager::class, $gatewayManager);

    $result = app(CashierProcessor::class)->checkStatus('pay_123');

    expect($result->status)->toBe(PaymentStatus::Completed)
        ->and($result->paymentId)->toBe('pay_123')
        ->and($result->amount)->toBe(2500)
        ->and($result->currency)->toBe('MYR');
});
