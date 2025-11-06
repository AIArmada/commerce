<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\DataObjects\ClientDetails;
use AIArmada\Chip\DataObjects\Product;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

function chipPurchaseResponse(array $overrides = []): array
{
    $base = [
        'id' => 'purchase_123',
        'created_on' => strtotime('2024-01-01T00:00:00Z'),
        'updated_on' => strtotime('2024-01-01T01:00:00Z'),
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'total' => 1000,
            'products' => [[
                'name' => 'Item',
                'price' => 1000,
                'quantity' => 1,
                'discount' => 0,
                'tax_percent' => 0.0,
            ]],
        ],
        'brand_id' => 'brand_123',
        'issuer_details' => [],
        'transaction_data' => [],
        'status' => 'created',
        'status_history' => [],
        'company_id' => 'company_123',
        'is_test' => true,
        'refund_availability' => 'all',
        'refundable_amount' => 1000,
        'payment_method_whitelist' => [],
    ];

    return array_replace_recursive($base, $overrides);
}

beforeEach(function (): void {
    $this->client = Mockery::mock(ChipCollectClient::class);
    $this->cache = Mockery::mock(CacheRepository::class);
    $this->apiWithCache = new PurchasesApi($this->cache, $this->client);
    $this->apiWithoutCache = new PurchasesApi(null, $this->client);
});

describe('Collect Purchases API', function (): void {
    it('creates a purchase with client payload', function (): void {
        $requestData = [
            'client' => ['email' => 'buyer@example.com'],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [['name' => 'Item', 'price' => 1000]],
            ],
            'brand_id' => 'brand_123',
        ];

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/', Mockery::on(function ($payload) {
                return $payload['brand_id'] === 'brand_123'
                    && $payload['client']['email'] === 'buyer@example.com';
            }))
            ->andReturn(chipPurchaseResponse());

        $purchase = $this->apiWithoutCache->create($requestData);

        expect($purchase->id)->toBe('purchase_123');
        expect($purchase->client->email)->toBe('buyer@example.com');
    });

    it('fills brand id from client when missing', function (): void {
        $this->client->shouldReceive('getBrandId')
            ->once()
            ->andReturn('brand_999');

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/', Mockery::on(function ($payload) {
                return $payload['brand_id'] === 'brand_999';
            }))
            ->andReturn(chipPurchaseResponse(['brand_id' => 'brand_999']));

        $purchase = $this->apiWithoutCache->create([
            'client' => ['email' => 'buyer@example.com'],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [['name' => 'Item', 'price' => 1000]],
            ],
        ]);

        expect($purchase->brand_id)->toBe('brand_999');
    });

    it('validates purchase payload requirements', function (): void {
        expect(fn () => $this->apiWithoutCache->create(['brand_id' => 'brand-test']))
            ->toThrow(ChipValidationException::class, 'Purchase payload is required');

        expect(fn () => $this->apiWithoutCache->create([
            'purchase' => ['products' => []],
            'brand_id' => 'brand',
        ]))->toThrow(ChipValidationException::class, 'Either client or client_id must be provided');

        expect(fn () => $this->apiWithoutCache->create([
            'client' => [],
            'purchase' => ['products' => []],
            'brand_id' => 'brand',
        ]))->toThrow(ChipValidationException::class, 'client.email is required when client payload is provided');
    });

    it('handles payment method caching', function (): void {
        $filters = ['currency' => 'MYR'];
        $paymentMethods = ['available_payment_methods' => ['fpx']];

        $this->cache->shouldReceive('remember')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('int'), Mockery::type('callable'))
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $this->client->shouldReceive('get')
            ->once()
            ->with('payment_methods/?currency=MYR')
            ->andReturn($paymentMethods);

        expect($this->apiWithCache->paymentMethods($filters))->toBe($paymentMethods);

        $this->client->shouldReceive('get')
            ->once()
            ->with('payment_methods/')
            ->andReturn($paymentMethods);

        expect($this->apiWithoutCache->paymentMethods())->toBe($paymentMethods);
    });

    it('creates checkout purchases with derived options', function (): void {
        $client = ClientDetails::fromArray(['email' => 'buyer@example.com']);
        $products = [new Product('Service', '1', 2000, 0, 0.0, null)];

        $this->client->shouldReceive('getBrandId')
            ->andReturn('brand_checkout');

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/', Mockery::on(function ($payload) {
                return $payload['brand_id'] === 'brand_checkout'
                    && $payload['send_receipt'] === true
                    && $payload['payment_method_whitelist'] === ['fpx', 'grabpay']
                    && $payload['success_redirect'] === 'https://example.com/success';
            }))
            ->andReturn(chipPurchaseResponse(['brand_id' => 'brand_checkout']));

        $purchase = $this->apiWithoutCache->createCheckoutPurchase(
            $products,
            $client,
            [
                'payment_method_whitelist' => 'fpx, grabpay',
                'success_redirect' => 'https://example.com/success',
            ]
        );

        expect($purchase->brand_id)->toBe('brand_checkout');
    });

    it('provides public key regardless of API response shape', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('public_key/')
            ->andReturn(['public_key' => 'test-key']);

        expect($this->apiWithoutCache->publicKey())->toBe('test-key');

        $this->client->shouldReceive('get')
            ->once()
            ->with('public_key/')
            ->andReturn('-----BEGIN PUBLIC KEY-----...');

        expect($this->apiWithoutCache->publicKey())
            ->toBe('-----BEGIN PUBLIC KEY-----...');
    });

    it('logs failures when deleting recurring tokens', function (): void {
        Log::spy();

        $this->client->shouldReceive('delete')
            ->once()
            ->with('purchases/purchase_999/recurring_token/')
            ->andThrow(new RuntimeException('API failure'));

        expect(fn () => $this->apiWithoutCache->deleteRecurringToken('purchase_999'))
            ->toThrow(RuntimeException::class);

        Log::shouldHaveLogged('error', function ($message, $context) {
            return str_contains($message, 'Failed to delete CHIP recurring token')
                && $context['purchase_id'] === 'purchase_999';
        });
    });

    it('retrieves an existing purchase', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('purchases/purchase_find/')
            ->andReturn(chipPurchaseResponse(['id' => 'purchase_find']));

        $purchase = $this->apiWithoutCache->find('purchase_find');

        expect($purchase->id)->toBe('purchase_find');
    });

    it('cancels a purchase and returns updated status', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_cancel/cancel/')
            ->andReturn(chipPurchaseResponse(['status' => 'cancelled']));

        $purchase = $this->apiWithoutCache->cancel('purchase_cancel');

        expect($purchase->status)->toBe('cancelled');
    });

    it('charges a purchase using recurring token payload', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_charge/charge/', ['recurring_token' => 'token_123'])
            ->andReturn(chipPurchaseResponse(['status' => 'charged']));

        $purchase = $this->apiWithoutCache->charge('purchase_charge', 'token_123');

        expect($purchase->status)->toBe('charged');
    });

    it('captures a purchase with and without amount', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_capture/capture/', ['amount' => 250])
            ->andReturn(chipPurchaseResponse(['refundable_amount' => 750]));

        $partialCapture = $this->apiWithoutCache->capture('purchase_capture', 250);
        expect($partialCapture->refundable_amount)->toBe(750);

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_capture/capture/', [])
            ->andReturn(chipPurchaseResponse(['status' => 'captured']));

        $fullCapture = $this->apiWithoutCache->capture('purchase_capture');
        expect($fullCapture->status)->toBe('captured');
    });

    it('releases a purchase hold', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_release/release/')
            ->andReturn(chipPurchaseResponse(['status' => 'released']));

        $purchase = $this->apiWithoutCache->release('purchase_release');

        expect($purchase->status)->toBe('released');
    });

    it('resends a purchase invoice', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_invoice/resend_invoice/')
            ->andReturn(chipPurchaseResponse(['status_history' => ['resent']]));

        $purchase = $this->apiWithoutCache->resendInvoice('purchase_invoice');

        expect($purchase->status_history)->toContain('resent');
    });

    it('deletes a recurring token successfully', function (): void {
        $this->client->shouldReceive('delete')
            ->once()
            ->with('purchases/purchase_token/recurring_token/')
            ->andReturnNull();

        expect(fn () => $this->apiWithoutCache->deleteRecurringToken('purchase_token'))
            ->not->toThrow(Exception::class);
    });

    it('supports refunding and marking purchases as paid', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/refund/', ['amount' => 500])
            ->andReturn(chipPurchaseResponse(['id' => 'purchase_123', 'refundable_amount' => 500]));

        $purchase = $this->apiWithoutCache->refund('purchase_123', 500);
        expect($purchase->refundable_amount)->toBe(500);

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/mark_as_paid/', ['paid_on' => 1704067200])
            ->andReturn(chipPurchaseResponse(['status' => 'paid']));

        $marked = $this->apiWithoutCache->markAsPaid('purchase_123', 1704067200);
        expect($marked->status)->toBe('paid');
    });
});
