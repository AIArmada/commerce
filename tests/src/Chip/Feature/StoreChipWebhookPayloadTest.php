<?php

declare(strict_types=1);

use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Features\Collect\Actions\MarkPurchaseAsPaid;
use AIArmada\Chip\Features\Collect\Actions\StoreChipWebhookPayload;
use AIArmada\Chip\Features\Collect\Api\PurchaseMirror;
use AIArmada\Chip\Features\Collect\Models\Payment;
use AIArmada\Chip\Features\Collect\Models\Purchase;
use AIArmada\Chip\Features\WebhookDelivery\Models\Webhook;
use AIArmada\Chip\Services\ChipCollectService;

function storeChipPurchasePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'purchase-store-' . uniqid(),
        'type' => 'purchase',
        'created_on' => 1700000000,
        'updated_on' => 1700000000,
        'brand_id' => 'brand-store',
        'status' => 'paid',
        'client' => [],
        'purchase' => [
            'currency' => 'MYR',
            'total' => 5000,
        ],
        'transaction_data' => [
            'payment_method' => 'visa',
        ],
        'status_history' => [],
        'platform' => 'api',
        'product' => 'purchases',
        'refund_availability' => 'all',
        'refundable_amount' => 5000,
    ], $overrides);
}

it('keeps refunded purchases terminal when a newer ordinary update arrives', function (): void {
    $store = new StoreChipWebhookPayload;
    $id = 'purchase-refunded-terminal';

    $store->storePurchasePayload(storeChipPurchasePayload([
        'id' => $id,
        'status' => 'refunded',
        'updated_on' => 1700000200,
    ]));

    $store->storePurchasePayload(storeChipPurchasePayload([
        'id' => $id,
        'status' => 'paid',
        'updated_on' => 1700000300,
    ]));

    $purchase = Purchase::query()->findOrFail($id);

    expect($purchase->status)->toBe('refunded')
        ->and($purchase->payment_method)->toBe('visa');
});

it('persists the provider payment method and falls back to the payment amount for the total', function (): void {
    $store = new StoreChipWebhookPayload;
    $id = 'purchase-payment-total';

    $payload = storeChipPurchasePayload([
        'id' => $id,
        'purchase' => [
            'currency' => 'MYR',
        ],
        'payment' => [
            'amount' => 3000,
        ],
    ]);

    unset($payload['purchase']['total']);

    $store->storePurchasePayload($payload);

    $purchase = Purchase::query()->findOrFail($id);
    $payment = Payment::query()->where('purchase_id', $id)->first();

    expect($purchase->total_minor)->toBe(3000)
        ->and($purchase->payment_method)->toBe('visa')
        ->and($payment)->not->toBeNull()
        ->and($payment?->currency)->toBe('MYR')
        ->and((int) $payment?->amount)->toBe(3000);
});

it('keeps webhook signatures and transport payloads out of default model serialization', function (): void {
    $webhook = Webhook::forceCreate([
        'title' => 'Serialization webhook',
        'event_type' => 'purchase.paid',
        'events' => ['purchase.paid'],
        'payload' => ['recurring_token' => 'tok_secret'],
        'headers' => ['X-Signature' => 'sig'],
        'signature' => 'sig',
        'public_key' => 'public-key',
        'status' => 'processed',
        'created_on' => time(),
        'updated_on' => time(),
        'callback' => 'http://example.com/webhook',
    ]);

    $serialized = json_encode($webhook, JSON_THROW_ON_ERROR);

    expect($serialized)->not->toContain('tok_secret')
        ->and($serialized)->not->toContain('sig')
        ->and($serialized)->not->toContain('public-key');
});

it('refuses to mark a refunded purchase as paid', function (): void {
    $store = new StoreChipWebhookPayload;
    $id = 'purchase-refunded-offline';

    $store->storePurchasePayload(storeChipPurchasePayload([
        'id' => $id,
        'status' => 'refunded',
    ]));

    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldNotReceive('markPurchaseAsPaid');

    $action = new MarkPurchaseAsPaid($service, app(PurchaseMirror::class));

    expect(fn () => $action->handle($id))
        ->toThrow(ChipValidationException::class, 'cannot be marked as paid');
});
