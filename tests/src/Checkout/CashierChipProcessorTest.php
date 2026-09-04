<?php

declare(strict_types=1);

use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\Payment\CashierChipProcessor;
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
