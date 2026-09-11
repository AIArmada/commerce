<?php

declare(strict_types=1);

use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\ChipPurchasePayloadBuilder;
use Illuminate\Support\Facades\Log;

it('warns when checkout derives the CHIP key and leaves the payload unchanged', function (): void {
    $session = new CheckoutSession;
    $session->id = 'checkout-derived-key-test';
    $request = new PaymentRequest(
        amount: 1250,
        currency: 'MYR',
        gateway: 'chip',
        description: 'Derived key test',
        customerEmail: 'customer@example.com',
        customerName: 'Test Customer',
        customerPhone: '+60123456789',
        successUrl: 'https://example.com/success',
        failureUrl: 'https://example.com/failure',
        cancelUrl: 'https://example.com/cancel',
    );

    Log::spy();

    $payload = (new ChipPurchasePayloadBuilder)->build($session, $request);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Checkout derived an idempotency key from the session.', [
            'checkout_session_id' => 'checkout-derived-key-test',
        ]);

    expect($payload)->toBe([
        'purchase' => [
            'products' => [[
                'name' => 'Derived key test',
                'price' => 1250,
                'quantity' => 1,
            ]],
            'currency' => 'MYR',
        ],
        'client' => [
            'email' => 'customer@example.com',
            'full_name' => 'Test Customer',
            'phone' => '+60123456789',
        ],
        'reference' => 'chk_checkout-derived-key-test',
        'idempotency_key' => 'checkout-derived-key-test',
        'success_redirect' => 'https://example.com/success',
        'failure_redirect' => 'https://example.com/failure',
        'cancel_redirect' => 'https://example.com/cancel',
    ]);
});
