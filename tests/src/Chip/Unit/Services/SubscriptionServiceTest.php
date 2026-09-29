<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\SubscriptionService;

beforeEach(function (): void {
    $this->chipService = Mockery::mock(ChipCollectService::class);
    $this->service = new SubscriptionService($this->chipService);
});

afterEach(function (): void {
    Mockery::close();
});

function subscriptionFakePurchase(string $id, array $overrides = []): PurchaseData
{
    $base = [
        'id' => $id,
        'type' => 'purchase',
        'brand_id' => $overrides['brand_id'] ?? 'brand_default',
        'client' => [
            'email' => 'subscriber@example.com',
        ],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [[
                'name' => 'Subscription',
                'price' => 1000,
                'quantity' => 1,
            ]],
            'total' => 1000,
        ],
        'created_on' => time(),
        'updated_on' => time(),
        'status' => 'created',
        'status_history' => [],
        'company_id' => 'company_123',
        'is_test' => true,
        'send_receipt' => false,
        'is_recurring_token' => false,
        'recurring_token' => null,
        'skip_capture' => false,
        'force_recurring' => false,
        'reference_generated' => 'REF',
        'refund_availability' => 'all',
        'refundable_amount' => 0,
        'payment_method_whitelist' => [],
        'platform' => 'api',
        'product' => 'subscription',
        'marked_as_paid' => false,
    ];

    return PurchaseData::from(array_replace_recursive($base, $overrides));
}

it('creates a free trial purchase with sensible defaults', function (): void {
    $data = [
        'client' => ['email' => 'trial@example.com'],
        'brand_id' => 'brand_trial',
        'trial_product_name' => 'Intro Trial',
    ];

    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->withArgs(function (array $payload) {
            expect($payload['client']['email'])->toBe('trial@example.com');
            expect($payload['brand_id'])->toBe('brand_trial');
            expect($payload['skip_capture'])->toBeTrue();
            expect($payload['payment_method_whitelist'])->toBe(['visa', 'mastercard', 'maestro']);
            expect($payload['purchase']['products'][0]['name'])->toBe('Intro Trial');
            expect($payload['purchase']['products'][0]['price'])->toBe(0);

            return true;
        })
        ->andReturn(subscriptionFakePurchase('trial_purchase'));

    $purchase = $this->service->createWithFreeTrial($data);

    expect($purchase->id)->toBe('trial_purchase');
});

it('creates a registration purchase with the provided fee and forces recurring', function (): void {
    $data = [
        'client' => ['email' => 'register@example.com'],
        'brand_id' => 'brand_register',
        'registration_fee' => 7500,
        'registration_product_name' => 'Registration Fee',
        'payment_method_whitelist' => ['visa'],
    ];

    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->withArgs(function (array $payload) {
            expect($payload['brand_id'])->toBe('brand_register');
            expect($payload['force_recurring'])->toBeTrue();
            expect($payload['purchase']['products'][0]['price'])->toBe(7500);
            expect($payload['payment_method_whitelist'])->toBe(['visa']);

            return true;
        })
        ->andReturn(subscriptionFakePurchase('registration_purchase'));

    $purchase = $this->service->createWithRegistrationFee($data);

    expect($purchase->id)->toBe('registration_purchase');
});

it('creates a subscription payment purchase for subsequent billing cycles', function (): void {
    $data = [
        'client' => ['email' => 'subscriber@example.com'],
        'amount' => 12000,
        'product_name' => 'Monthly Plan',
        'brand_id' => 'brand_subscription',
    ];

    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->withArgs(function (array $payload) {
            expect($payload['brand_id'])->toBe('brand_subscription');
            expect($payload['purchase']['products'][0]['price'])->toBe(12000);
            expect($payload['purchase']['products'][0]['name'])->toBe('Monthly Plan');

            return true;
        })
        ->andReturn(subscriptionFakePurchase('subscription_purchase'));

    $purchase = $this->service->createSubscriptionPayment($data);

    expect($purchase->id)->toBe('subscription_purchase');
});

it('charges an existing subscription using the chip service', function (): void {
    $this->chipService->shouldReceive('chargePurchase')
        ->once()
        ->with('sub_123', 'token_abc')
        ->andReturn(subscriptionFakePurchase('charged_purchase'));

    $purchase = $this->service->chargeSubscription('sub_123', 'token_abc');

    expect($purchase->id)->toBe('charged_purchase');
});

it('creates a monthly subscription starting with a free trial', function (): void {
    $data = [
        'client' => ['email' => 'bundle@example.com'],
        'trial_days' => 14,
        'amount' => 4500,
        'brand_id' => 'brand_monthly',
        'product_name' => 'Bundle Plan',
    ];

    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->ordered()
        ->withArgs(function (array $payload) {
            expect($payload['skip_capture'])->toBeTrue();
            expect($payload['purchase']['products'][0]['price'])->toBe(0);

            return true;
        })
        ->andReturn(subscriptionFakePurchase('trial_purchase', ['brand_id' => 'brand_monthly']));

    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->ordered()
        ->withArgs(function (array $payload) {
            expect($payload['purchase']['products'][0]['price'])->toBe(4500);
            expect($payload['purchase']['products'][0]['name'])->toBe('Bundle Plan');

            return true;
        })
        ->andReturn(subscriptionFakePurchase('subscription_purchase', ['brand_id' => 'brand_monthly']));

    $result = $this->service->createMonthlySubscription($data);

    expect($result['initial_purchase']->id)->toBe('trial_purchase');
    expect($result['subscription_purchase']->id)->toBe('subscription_purchase');
});

it('creates a monthly subscription starting with a registration fee when no trial provided', function (): void {
    $data = [
        'client' => ['email' => 'reg@example.com'],
        'registration_fee' => 5500,
        'amount' => 9900,
        'brand_id' => 'brand_monthly_reg',
    ];

    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->ordered()
        ->withArgs(function (array $payload) {
            expect($payload['force_recurring'])->toBeTrue();
            expect($payload['purchase']['products'][0]['price'])->toBe(5500);

            return true;
        })
        ->andReturn(subscriptionFakePurchase('registration_purchase', ['brand_id' => 'brand_monthly_reg']));

    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->ordered()
        ->withArgs(function (array $payload) {
            expect($payload['purchase']['products'][0]['price'])->toBe(9900);

            return true;
        })
        ->andReturn(subscriptionFakePurchase('recurring_purchase', ['brand_id' => 'brand_monthly_reg']));

    $result = $this->service->createMonthlySubscription($data);

    expect($result['initial_purchase']->id)->toBe('registration_purchase');
    expect($result['subscription_purchase']->id)->toBe('recurring_purchase');
});

it('resolves the brand id from the chip service when not provided', function (): void {
    $data = [
        'client' => ['email' => 'fallback@example.com'],
        'trial_product_name' => 'Fallback Trial',
    ];

    $this->chipService->shouldReceive('getBrandId')
        ->once()
        ->andReturn('brand_from_service');

    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->withArgs(function (array $payload) {
            expect($payload['brand_id'])->toBe('brand_from_service');

            return true;
        })
        ->andReturn(subscriptionFakePurchase('fallback_purchase', ['brand_id' => 'brand_from_service']));

    $purchase = $this->service->createWithFreeTrial($data);

    expect($purchase->brand_id)->toBe('brand_from_service');
});

it('throws a validation exception when brand id cannot be resolved', function (): void {
    $this->chipService->shouldReceive('getBrandId')
        ->once()
        ->andReturn('');

    $this->chipService->shouldReceive('createPurchase')->never();

    $this->service->createWithFreeTrial([
        'client' => ['email' => 'missing@example.com'],
    ]);
})->throws(ChipValidationException::class);

it('requires either trial days or registration fee when creating monthly subscription', function (): void {
    $this->chipService->shouldReceive('createPurchase')->never();

    $this->service->createMonthlySubscription([
        'client' => ['email' => 'invalid@example.com'],
        'amount' => 5000,
        'brand_id' => 'brand_invalid',
    ]);
})->throws(InvalidArgumentException::class);

it('forwards the leaf reference verbatim as the idempotency key', function (): void {
    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->withArgs(function (array $payload, ?string $key): bool {
            return $key === 'sub-ref-1';
        })
        ->andReturn(subscriptionFakePurchase('trial_purchase'));

    $this->service->createWithFreeTrial([
        'client' => ['email' => 'ref@example.com'],
        'trial_days' => 7,
        'brand_id' => 'brand_ref',
        'reference' => 'sub-ref-1',
    ]);
});

it('prefers the explicit leaf key over the reference', function (): void {
    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->withArgs(function (array $payload, ?string $key): bool {
            return $key === 'explicit-leaf-1';
        })
        ->andReturn(subscriptionFakePurchase('trial_purchase'));

    $this->service->createWithFreeTrial([
        'client' => ['email' => 'ref@example.com'],
        'trial_days' => 7,
        'brand_id' => 'brand_ref',
        'reference' => 'sub-ref-1',
    ], 'explicit-leaf-1');
});

it('rejects a non-string leaf reference', function (): void {
    $this->chipService->shouldReceive('createPurchase')->never();

    $this->service->createWithFreeTrial([
        'client' => ['email' => 'ref@example.com'],
        'trial_days' => 7,
        'brand_id' => 'brand_ref',
        'reference' => 42,
    ]);
})->throws(ChipValidationException::class);

it('derives monthly child keys from the reference', function (): void {
    $data = [
        'client' => ['email' => 'child@example.com'],
        'trial_days' => 14,
        'amount' => 4500,
        'brand_id' => 'brand_child',
        'reference' => 'sub-monthly-1',
    ];

    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->ordered()
        ->withArgs(function (array $payload, ?string $key): bool {
            return ($payload['skip_capture'] ?? null) === true
                && $key === 'sub-monthly-1-initial'
                && ! array_key_exists('reference', $payload);
        })
        ->andReturn(subscriptionFakePurchase('trial_purchase', ['brand_id' => 'brand_child']));

    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->ordered()
        ->withArgs(function (array $payload, ?string $key): bool {
            return ($payload['purchase']['products'][0]['price'] ?? null) === 4500
                && $key === 'sub-monthly-1-subscription'
                && ! array_key_exists('reference', $payload);
        })
        ->andReturn(subscriptionFakePurchase('subscription_purchase', ['brand_id' => 'brand_child']));

    $result = $this->service->createMonthlySubscription($data);

    expect($result['initial_purchase']->id)->toBe('trial_purchase');
    expect($result['subscription_purchase']->id)->toBe('subscription_purchase');
});

it('forwards the caller currency into the built payload', function (): void {
    $this->chipService->shouldReceive('createPurchase')
        ->once()
        ->withArgs(function (array $payload): bool {
            return ($payload['purchase']['currency'] ?? null) === 'MYR';
        })
        ->andReturn(subscriptionFakePurchase('trial_purchase'));

    $this->service->createWithFreeTrial([
        'client' => ['email' => 'cur@example.com'],
        'trial_days' => 7,
        'brand_id' => 'brand_cur',
        'currency' => 'MYR',
    ]);
});

it('keeps monthly purchases keyless without a reference', function (): void {
    $data = [
        'client' => ['email' => 'nokey@example.com'],
        'trial_days' => 14,
        'amount' => 4500,
        'brand_id' => 'brand_nokey',
    ];

    $this->chipService->shouldReceive('createPurchase')
        ->twice()
        ->withArgs(function (array $payload, ?string $key): bool {
            return $key === null;
        })
        ->andReturn(subscriptionFakePurchase('purchase_nokey', ['brand_id' => 'brand_nokey']));

    $result = $this->service->createMonthlySubscription($data);

    expect($result['initial_purchase']->id)->toBe('purchase_nokey');
});
