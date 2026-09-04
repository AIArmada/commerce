<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Contracts\PaymentContract;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
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
    /** @var Expectation $gatewayName */
    $gatewayName = $payment->shouldReceive('gateway');
    $gatewayName->once()->andReturn('chip');

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
        ->and($result->currency)->toBe('MYR')
        ->and($result->provider)->toBe('chip');
});

it('creates payments through the unified billable and payment contracts', function (): void {
    $billable = mock(BillableContract::class);
    $payment = mock(PaymentContract::class);

    $payment->shouldReceive('toArray')
        ->once()
        ->andReturn([
            'id' => 'pay_456',
            'status' => 'succeeded',
            'currency' => 'MYR',
            'raw_amount' => 2500,
        ]);
    $payment->shouldReceive('id')->once()->andReturn('pay_456');
    $payment->shouldReceive('redirectUrl')->once()->andReturn(null);
    $payment->shouldReceive('status')->once()->andReturn('succeeded');
    $payment->shouldReceive('rawAmount')->once()->andReturn(2500);
    $payment->shouldReceive('currency')->once()->andReturn('MYR');
    $payment->shouldReceive('isSucceeded')->once()->andReturn(true);

    $gateway = mock(GatewayContract::class);
    $gateway->shouldReceive('name')->once()->andReturn('chip');
    $gateway->shouldReceive('charge')
        ->once()
        ->with(
            $billable,
            2500,
            null,
            Mockery::on(fn (array $options): bool => $options['success_url'] === 'https://example.test/success'
                && $options['failure_url'] === 'https://example.test/failure'
                && $options['cancel_url'] === 'https://example.test/cancel'
                && $options['currency'] === 'MYR'
                && $options['metadata']['checkout_session_id'] === 'session-456'),
        )
        ->andReturn($payment);

    $gatewayManager = mock(GatewayManager::class);
    $gatewayManager->shouldReceive('gateway')->once()->with(null)->andReturn($gateway);
    app()->instance(GatewayManager::class, $gatewayManager);

    $session = new CheckoutSession;
    $session->setRelation('billable', $billable);
    $session->setRelation('customer', null);
    $session->setAttribute('id', 'session-456');

    $result = app(CashierProcessor::class)->createPayment(
        $session,
        new PaymentRequest(
            amount: 2500,
            currency: 'MYR',
            gateway: 'cashier',
            description: 'Event admission',
            successUrl: 'https://example.test/success',
            failureUrl: 'https://example.test/failure',
            cancelUrl: 'https://example.test/cancel',
            metadata: ['checkout_session_id' => 'session-456'],
        ),
    );

    expect($result->status)->toBe(PaymentStatus::Completed)
        ->and($result->paymentId)->toBe('pay_456')
        ->and($result->amount)->toBe(2500)
        ->and($result->currency)->toBe('MYR')
        ->and($result->provider)->toBe('chip');
});

it('keeps an unrecognised cashier refund status in processing', function (): void {
    $gateway = mock(GatewayContract::class);
    $gateway->shouldReceive('refund')
        ->once()
        ->with('pay_original', 500)
        ->andReturn([
            'id' => 'refund_123',
            'status' => 'provider_new_status',
        ]);
    $gateway->shouldReceive('name')->once()->andReturn('stripe');

    $gatewayManager = mock(GatewayManager::class);
    $gatewayManager->shouldReceive('gateway')
        ->once()
        ->with('stripe')
        ->andReturn($gateway);

    app()->instance(GatewayManager::class, $gatewayManager);

    $result = app(CashierProcessor::class)->refundForProvider('stripe', 'pay_original', 500);

    expect($result->status)->toBe(PaymentStatus::Processing)
        ->and($result->transactionId)->toBe('refund_123')
        ->and($result->paymentId)->toBe('pay_original')
        ->and($result->provider)->toBe('stripe');
});

it('marks a recognised cashier refund status as refunded', function (): void {
    $gateway = mock(GatewayContract::class);
    $gateway->shouldReceive('refund')
        ->once()
        ->with('pay_original', 500)
        ->andReturn([
            'id' => 'refund_123',
            'status' => 'refunded',
        ]);
    $gateway->shouldReceive('name')->once()->andReturn('stripe');

    $gatewayManager = mock(GatewayManager::class);
    $gatewayManager->shouldReceive('gateway')
        ->once()
        ->with('stripe')
        ->andReturn($gateway);

    app()->instance(GatewayManager::class, $gatewayManager);

    $result = app(CashierProcessor::class)->refundForProvider('stripe', 'pay_original', 500);

    expect($result->status)->toBe(PaymentStatus::Refunded)
        ->and($result->transactionId)->toBe('refund_123');
});

it('uses the payment contract refund state for a refreshed Stripe payment', function (): void {
    $refund = mock(PaymentContract::class);
    $refund->shouldReceive('toArray')->once()->andReturn([
        'id' => 'pay_original',
        'status' => 'succeeded',
    ]);
    $refund->shouldReceive('status')->once()->andReturn('succeeded');
    $refund->shouldReceive('isRefunded')->once()->andReturnTrue();
    $refund->shouldReceive('id')->once()->andReturn('pay_original');

    $gateway = mock(GatewayContract::class);
    $gateway->shouldReceive('refund')
        ->once()
        ->with('pay_original', 500)
        ->andReturn($refund);
    $gateway->shouldReceive('name')->once()->andReturn('stripe');

    $gatewayManager = mock(GatewayManager::class);
    $gatewayManager->shouldReceive('gateway')
        ->once()
        ->with('stripe')
        ->andReturn($gateway);

    app()->instance(GatewayManager::class, $gatewayManager);

    $result = app(CashierProcessor::class)->refundForProvider('stripe', 'pay_original', 500);

    expect($result->status)->toBe(PaymentStatus::Refunded)
        ->and($result->transactionId)->toBeNull();
});
