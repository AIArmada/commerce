<?php

declare(strict_types=1);

use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\Payment\CashierChipProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Facades\Chip;

it('stores a JSON-safe public CHIP purchase response when checking payment status', function (): void {
    $purchase = PurchaseData::from([
        'id' => 'purchase-123',
        'type' => 'purchase',
        'status' => 'paid',
        'reference' => 'checkout-reference',
        'created_on' => time(),
        'updated_on' => time(),
        'client' => [
            'email' => 'test@example.com',
            'full_name' => 'Test Customer',
        ],
        'purchase' => [
            'total' => 1000,
            'currency' => 'MYR',
            'products' => [],
        ],
    ]);

    Chip::shouldReceive('getPurchase')
        ->once()
        ->with('purchase-123')
        ->andReturn($purchase);

    $result = app(CashierChipProcessor::class)->checkStatus('purchase-123');

    $json = json_encode($result->gatewayResponse, JSON_THROW_ON_ERROR);

    expect($result->gatewayResponse)->toBe($purchase->toArray())
        ->and($json)->toBeString();
});

it('passes the checkout session key to guest CHIP purchases', function (): void {
    $purchase = PurchaseData::from([
        'id' => 'purchase-guest-123',
        'type' => 'purchase',
        'status' => 'created',
        'created_on' => time(),
        'updated_on' => time(),
        'client' => [
            'email' => 'guest@example.com',
            'full_name' => 'Guest Customer',
        ],
        'purchase' => [
            'total' => 1000,
            'currency' => 'MYR',
            'products' => [],
        ],
        'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase-guest-123',
    ]);

    Chip::shouldReceive('createPurchase')
        ->once()
        ->with(Mockery::on(fn (array $payload): bool => $payload['idempotency_key'] === 'guest-session-123'))
        ->andReturn($purchase);

    $session = new CheckoutSession;
    $session->setAttribute('id', 'guest-session-123');
    $session->setRelation('billable', null);
    $session->setRelation('customer', null);

    $result = app(CashierChipProcessor::class)->createPayment(
        $session,
        new PaymentRequest(
            amount: 1000,
            currency: 'MYR',
            gateway: 'cashier-chip',
            description: 'Guest payment',
            successUrl: 'https://example.test/success',
            failureUrl: 'https://example.test/failure',
            cancelUrl: 'https://example.test/cancel',
        ),
    );

    expect($result->paymentId)->toBe('purchase-guest-123')
        ->and($result->redirectUrl)->toBe('https://gate.chip-in.asia/checkout/purchase-guest-123');
});

it('keeps a pending CHIP refund in processing', function (): void {
    $purchase = PurchaseData::from([
        'id' => 'purchase-unknown',
        'type' => 'purchase',
        'status' => 'pending_refund',
        'reference' => 'checkout-reference',
        'created_on' => time(),
        'updated_on' => time(),
        'client' => [
            'email' => 'test@example.com',
            'full_name' => 'Test Customer',
        ],
        'purchase' => [
            'total' => 500,
            'currency' => 'MYR',
            'products' => [],
        ],
    ]);

    Chip::shouldReceive('refundPurchase')
        ->once()
        ->with('purchase-unknown', 500)
        ->andReturn($purchase);

    $result = app(CashierChipProcessor::class)->refund('purchase-unknown', 500);

    expect($result->status)->toBe(PaymentStatus::Processing)
        ->and($result->paymentId)->toBe('purchase-unknown')
        ->and($result->transactionId)->toBeNull();
});

it('marks a completed CHIP refund and keeps its refund payment id', function (): void {
    $refund = PaymentData::from([
        'id' => 'payment-refund-123',
        'type' => 'payment',
        'status' => 'refunded',
        'payment' => [
            'amount' => 500,
            'currency' => 'MYR',
            'net_amount' => 500,
            'fee_amount' => 0,
            'pending_amount' => 0,
            'payment_type' => 'refund',
            'is_outgoing' => true,
        ],
        'related_to' => [
            'type' => 'purchase',
            'id' => 'purchase-123',
        ],
    ]);

    Chip::shouldReceive('refundPurchase')
        ->once()
        ->with('purchase-123', 500)
        ->andReturn($refund);

    $result = app(CashierChipProcessor::class)->refund('purchase-123', 500);

    expect($result->status)->toBe(PaymentStatus::Refunded)
        ->and($result->paymentId)->toBe('purchase-123')
        ->and($result->transactionId)->toBe('payment-refund-123');
});
