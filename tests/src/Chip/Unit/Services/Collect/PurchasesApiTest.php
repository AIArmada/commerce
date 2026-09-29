<?php

declare(strict_types=1);

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Data\ClientDetailsData;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
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

function chipPaymentResponse(array $overrides = []): array
{
    $base = [
        'id' => 'payment_123',
        'type' => 'payment',
        'created_on' => strtotime('2024-01-01T00:00:00Z'),
        'updated_on' => strtotime('2024-01-01T01:00:00Z'),
        'client' => ['email' => 'buyer@example.com'],
        'payment' => [
            'amount' => 500,
            'currency' => 'MYR',
            'net_amount' => 500,
            'fee_amount' => 0,
            'pending_amount' => 0,
            'payment_type' => 'refund',
            'is_outgoing' => true,
            'paid_on' => strtotime('2024-01-01T00:30:00Z'),
            'remote_paid_on' => strtotime('2024-01-01T00:30:00Z'),
        ],
        'transaction_data' => [],
        'related_to' => [
            'type' => 'purchase',
            'id' => 'purchase_123',
        ],
        'reference_generated' => 'REFUND_123',
        'reference' => 'REF_123',
        'account_id' => 'account_123',
        'company_id' => 'company_123',
        'is_test' => true,
        'user_id' => null,
        'brand_id' => 'brand_123',
        'status' => 'refunded',
    ];

    return array_replace_recursive($base, $overrides);
}

function chipValidCreatePayload(array $overrides = []): array
{
    $base = [
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
        ],
        'brand_id' => 'brand_123',
    ];

    return array_replace_recursive($base, $overrides);
}

beforeEach(function (): void {
    $this->client = Mockery::mock(ChipCollectClient::class);
    $this->cache = Mockery::mock(CacheRepository::class);
    $this->apiWithCache = new PurchasesApi($this->cache, $this->client);
    $this->apiWithoutCache = new PurchasesApi(null, $this->client);
});

it('rejects mixed-currency checkout products in the collect API', function (): void {
    $client = ClientDetailsData::from(['email' => 'buyer@example.com']);
    $products = [
        ProductData::from(['name' => 'MYR item', 'price' => 1000, 'currency' => 'MYR']),
        ProductData::from(['name' => 'USD item', 'price' => 1000, 'currency' => 'USD']),
    ];

    expect(fn () => $this->apiWithoutCache->createCheckoutPurchase($products, $client))
        ->toThrow(ChipValidationException::class, 'Checkout product currency must match the purchase currency.');
});

it('rejects a checkout currency override that differs from its products', function (): void {
    $client = ClientDetailsData::from(['email' => 'buyer@example.com']);
    $products = [ProductData::from(['name' => 'MYR item', 'price' => 1000, 'currency' => 'MYR'])];

    expect(fn () => $this->apiWithoutCache->createCheckoutPurchase($products, $client, [
        'purchase_overrides' => ['currency' => 'USD'],
    ]))->toThrow(ChipValidationException::class, 'cannot differ from its products');
});

it('rejects a response with a different currency than the request', function (): void {
    $this->client->shouldReceive('post')
        ->once()
        ->andReturn(chipPurchaseResponse(['purchase' => ['currency' => 'USD']]));

    expect(fn () => $this->apiWithoutCache->create([
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
        ],
        'brand_id' => 'brand_123',
    ]))->toThrow(ChipValidationException::class, 'unexpected currency');
});

it('rejects an unreconciled stable total contract', function (): void {
    expect(fn () => $this->apiWithoutCache->create([
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
            'subtotal_override' => 1000,
            'total_discount_override' => 0,
            'total_tax_override' => 0,
            'total_override' => 999,
        ],
        'brand_id' => 'brand_123',
    ]))->toThrow(ChipValidationException::class, 'total overrides do not reconcile');
});

it('rejects over-cap client address fields', function (string $field, int $max): void {
    expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
        'client' => [$field => str_repeat('x', $max + 1)],
    ])))->toThrow(ChipValidationException::class, 'at most');
})->with([
    'street' => ['street_address', 128],
    'city' => ['city', 128],
    'zip' => ['zip_code', 32],
    'state' => ['state', 128],
    'shipping street' => ['shipping_street_address', 128],
    'shipping city' => ['shipping_city', 128],
    'shipping zip' => ['shipping_zip_code', 32],
    'shipping state' => ['shipping_state', 128],
]);

it('accepts at-cap client address fields', function (): void {
    $this->client->shouldReceive('post')
        ->once()
        ->andReturn(chipPurchaseResponse());

    $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
        'client' => [
            'street_address' => str_repeat('s', 128),
            'city' => str_repeat('c', 128),
            'zip_code' => str_repeat('z', 32),
            'state' => str_repeat('t', 128),
            'shipping_street_address' => str_repeat('s', 128),
            'shipping_city' => str_repeat('c', 128),
            'shipping_zip_code' => str_repeat('z', 32),
            'shipping_state' => str_repeat('t', 128),
        ],
    ]));

    expect($purchase->id)->toBe('purchase_123');
});

it('reconciles subtotal_override against per-product total price overrides', function (): void {
    $this->client->shouldReceive('post')
        ->once()
        ->andReturn(chipPurchaseResponse());

    $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
        'purchase' => [
            'products' => [
                ['name' => 'TPO item', 'price' => 1000, 'quantity' => 3, 'total_price_override' => 2500],
                ['name' => 'Plain item', 'price' => 100, 'quantity' => 5],
            ],
            'subtotal_override' => 3000,
        ],
    ]));

    expect($purchase->id)->toBe('purchase_123');
});

it('reports the override-based subtotal on mismatch', function (): void {
    $thrown = null;

    try {
        $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => [
                'products' => [['name' => 'TPO item', 'price' => 1000, 'quantity' => 3, 'total_price_override' => 2500]],
                'subtotal_override' => 2999,
            ],
        ]));
    } catch (ChipValidationException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(ChipValidationException::class);
    expect($thrown->getMessage())->toContain('subtotal override does not match');
    expect($thrown->getValidationErrors())->toBe(['line_items_subtotal' => 2500, 'subtotal_override' => 2999]);
});

it('treats a null per-product total price override as absent', function (): void {
    $this->client->shouldReceive('post')
        ->once()
        ->andReturn(chipPurchaseResponse());

    $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
        'purchase' => [
            'products' => [['name' => 'Item', 'price' => 1000, 'total_price_override' => null]],
            'subtotal_override' => 1000,
        ],
    ]));

    expect($purchase->id)->toBe('purchase_123');
});

it('rejects a negative per-product total price override', function (): void {
    expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
        'purchase' => [
            'products' => [['name' => 'TPO item', 'price' => 1000, 'total_price_override' => -1]],
        ],
    ])))->toThrow(ChipValidationException::class, 'total price override must be non-negative');
});

it('accepts a per-line discount above the unit price', function (): void {
    $this->client->shouldReceive('post')
        ->once()
        ->andReturn(chipPurchaseResponse());

    // The server bound is discount <= price x quantity (201, total 150).
    $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
        'purchase' => [
            'products' => [['name' => 'Item', 'price' => 100, 'discount' => 150, 'quantity' => 3]],
        ],
    ]));

    expect($purchase->id)->toBe('purchase_123');
});

it('accepts a discount equal to a fractional line gross', function (): void {
    $this->client->shouldReceive('post')
        ->once()
        ->andReturn(chipPurchaseResponse());

    // 200 x "1.005" is exactly 201; float64 lands just below and would reject.
    $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
        'purchase' => [
            'products' => [['name' => 'Item', 'price' => 200, 'discount' => 201, 'quantity' => '1.005']],
        ],
    ]));

    expect($purchase->id)->toBe('purchase_123');
});

it('rejects a discount above the line gross', function (): void {
    expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
        'purchase' => [
            'products' => [['name' => 'Item', 'price' => 100, 'discount' => 500, 'quantity' => 3]],
        ],
    ])))->toThrow(ChipValidationException::class, 'discount no greater than price times quantity');
});

it('rejects client and client_id set together', function (): void {
    expect(fn () => $this->apiWithoutCache->create([
        'client' => ['email' => 'buyer@example.com'],
        'client_id' => 'client_123',
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
        ],
        'brand_id' => 'brand_123',
    ]))->toThrow(ChipValidationException::class, 'Only one of client or client_id may be provided');
});

it('rejects client paired with an empty client_id', function (): void {
    expect(fn () => $this->apiWithoutCache->create([
        'client' => ['email' => 'buyer@example.com'],
        'client_id' => '',
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
        ],
        'brand_id' => 'brand_123',
    ]))->toThrow(ChipValidationException::class, 'Only one of client or client_id may be provided');
});

it('rejects client_id paired with an empty client array', function (): void {
    expect(fn () => $this->apiWithoutCache->create([
        'client' => [],
        'client_id' => 'client_123',
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
        ],
        'brand_id' => 'brand_123',
    ]))->toThrow(ChipValidationException::class, 'Only one of client or client_id may be provided');
});

it('treats an explicit null client_id as absent', function (): void {
    $this->client->shouldReceive('post')
        ->once()
        ->andReturn(chipPurchaseResponse());

    $purchase = $this->apiWithoutCache->create([
        'client' => ['email' => 'buyer@example.com'],
        'client_id' => null,
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
        ],
        'brand_id' => 'brand_123',
    ]);

    expect($purchase->id)->toBe('purchase_123');
});

it('rejects a response total that differs from total_override', function (): void {
    $this->client->shouldReceive('post')
        ->once()
        ->andReturn(chipPurchaseResponse(['purchase' => ['total' => 999]]));

    expect(fn () => $this->apiWithoutCache->create([
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
            'subtotal_override' => 1000,
            'total_discount_override' => 0,
            'total_tax_override' => 0,
            'total_override' => 1000,
        ],
        'brand_id' => 'brand_123',
    ]))->toThrow(ChipValidationException::class, 'unexpected total');
});

it('ignores a differing response total when no total_override is set', function (): void {
    $this->client->shouldReceive('post')
        ->once()
        ->andReturn(chipPurchaseResponse(['purchase' => ['total' => 999]]));

    $purchase = $this->apiWithoutCache->create([
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
        ],
        'brand_id' => 'brand_123',
    ]);

    expect($purchase->id)->toBe('purchase_123');
});

it('rejects purchase.total on the create path', function (): void {
    expect(fn () => $this->apiWithoutCache->create([
        'client' => ['email' => 'buyer@example.com'],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [['name' => 'Item', 'price' => 1000]],
            'total' => 1000,
        ],
        'brand_id' => 'brand_123',
    ]))->toThrow(ChipValidationException::class, 'server-calculated');
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

    it('reuses the original purchase for repeated idempotency references', function (): void {
        $cache = Cache::store('array');
        $cache->clear();
        $api = new PurchasesApi($cache, $this->client);
        $requestData = [
            'client' => ['email' => 'buyer@example.com'],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [['name' => 'Item', 'price' => 1000]],
            ],
            'brand_id' => 'brand_123',
            'reference' => 'checkout-session-123',
        ];

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/', $requestData, ['Idempotency-Key' => 'checkout-session-123'])
            ->andReturn(chipPurchaseResponse([
                'reference' => 'checkout-session-123',
            ]));

        $first = $api->create($requestData);
        $second = $api->create($requestData);

        expect($second->id)->toBe($first->id)
            ->and($second->reference)->toBe($first->reference);
    });

    it('rejects a reused idempotency key with a different payload', function (): void {
        $cache = Cache::store('array');
        $cache->clear();
        $api = new PurchasesApi($cache, $this->client);
        $requestData = [
            'client' => ['email' => 'buyer@example.com'],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [['name' => 'Item', 'price' => 1000]],
            ],
            'brand_id' => 'brand_123',
            'idempotency_key' => 'checkout-session-123',
        ];

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/', Mockery::on(function (array $payload): bool {
                return ! array_key_exists('idempotency_key', $payload);
            }), ['Idempotency-Key' => 'checkout-session-123'])
            ->andReturn(chipPurchaseResponse());

        $api->create($requestData);

        expect(fn () => $api->create([
            ...$requestData,
            'purchase' => [
                'currency' => 'MYR',
                'products' => [['name' => 'Different item', 'price' => 2000]],
            ],
        ]))->toThrow(
            ChipValidationException::class,
            'Idempotency key has already been used for a different purchase payload.'
        );
    });

    it('sends the explicit idempotency key as a header', function (): void {
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
            ->with('purchases/', $requestData, ['Idempotency-Key' => 'explicit-key-1'])
            ->andReturn(chipPurchaseResponse());

        $purchase = $this->apiWithoutCache->create($requestData, 'explicit-key-1');

        expect($purchase->id)->toBe('purchase_123');
    });

    it('sends no idempotency header for keyless creates', function (): void {
        $requestData = [
            'client' => ['email' => 'buyer@example.com'],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [['name' => 'Item', 'price' => 1000]],
            ],
            'brand_id' => 'brand_123',
        ];

        // Two-arg expectation: any header would fail the arity match.
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/', $requestData)
            ->andReturn(chipPurchaseResponse());

        $purchase = $this->apiWithoutCache->create($requestData);

        expect($purchase->id)->toBe('purchase_123');
    });

    it('sends the key as a header on purchase mutations', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/cancel/', [], ['Idempotency-Key' => 'cancel-key-1'])
            ->andReturn(chipPurchaseResponse(['id' => 'purchase_123']));

        $purchase = $this->apiWithoutCache->cancel('purchase_123', 'cancel-key-1');

        expect($purchase->id)->toBe('purchase_123');
    });

    it('rejects a blank mutation idempotency key', function (): void {
        $this->client->shouldNotReceive('post');

        expect(fn () => $this->apiWithoutCache->cancel('purchase_123', '  '))
            ->toThrow(ChipValidationException::class, 'non-empty string');
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
        $client = ClientDetailsData::from(['email' => 'buyer@example.com']);
        $products = [ProductData::from(['name' => 'Service', 'price' => 2000, 'quantity' => '1'])];

        $this->client->shouldReceive('getBrandId')
            ->andReturn('brand_checkout');

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/', Mockery::on(function ($payload) {
                return $payload['brand_id'] === 'brand_checkout'
                    && $payload['send_receipt'] === false
                    && $payload['payment_method_whitelist'] === ['fpx', 'grabpay']
                    && $payload['success_redirect'] === 'https://example.com/success';
            }), Mockery::on(function ($headers) {
                return ($headers['Idempotency-Key'] ?? null) !== null
                    && str_starts_with($headers['Idempotency-Key'], 'checkout-');
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

    it('omits null product fields from checkout payloads', function (): void {
        $client = ClientDetailsData::from(['email' => 'buyer@example.com']);
        $products = [ProductData::from(['name' => 'Service', 'price' => 2000, 'quantity' => '1'])];

        $this->client->shouldReceive('getBrandId')
            ->andReturn('brand_checkout');

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/', Mockery::on(function ($payload) {
                return ! array_key_exists('category', $payload['purchase']['products'][0])
                    && $payload['purchase']['products'][0]['discount'] === 0;
            }), Mockery::on(function ($headers) {
                return str_starts_with($headers['Idempotency-Key'] ?? '', 'checkout-');
            }))
            ->andReturn(chipPurchaseResponse(['brand_id' => 'brand_checkout']));

        $this->apiWithoutCache->createCheckoutPurchase($products, $client);
    });

    it('provides the PEM public key returned by the API', function (): void {
        $pem = "-----BEGIN PUBLIC KEY-----\ntest-key\n-----END PUBLIC KEY-----";

        $this->client->shouldReceive('get')
            ->once()
            ->with('public_key/')
            ->andReturn($pem);

        expect($this->apiWithoutCache->publicKey())->toBe($pem);
    });

    it('logs failures when deleting recurring tokens', function (): void {
        Log::spy();

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_999/delete_recurring_token/')
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
            ->andReturn(chipPurchaseResponse(['status' => 'paid']));

        $purchase = $this->apiWithoutCache->charge('purchase_charge', 'token_123');

        expect($purchase->status)->toBe('paid');
    });

    it('captures a purchase with and without amount', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_capture/capture/', ['amount' => 250])
            ->andReturn(chipPurchaseResponse(['refundable_amount' => 750]));

        $partialCapture = $this->apiWithoutCache->capture('purchase_capture', 250);
        expect($partialCapture->getRefundableAmountInCents())->toBe(750);

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_capture/capture/', [])
            ->andReturn(chipPurchaseResponse(['status' => 'paid']));

        $fullCapture = $this->apiWithoutCache->capture('purchase_capture');
        expect($fullCapture->status)->toBe('paid');
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
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_token/delete_recurring_token/')
            ->andReturn(chipPurchaseResponse([
                'id' => 'purchase_token',
                'is_recurring_token' => false,
            ]));

        $purchase = $this->apiWithoutCache->deleteRecurringToken('purchase_token');

        expect($purchase->id)->toBe('purchase_token');
        expect($purchase->is_recurring_token)->toBeFalse();
    });

    it('returns PaymentData for completed refund responses', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/refund/', ['amount' => 500])
            ->andReturn(chipPaymentResponse([
                'id' => 'payment_refund_123',
                'related_to' => ['type' => 'purchase', 'id' => 'purchase_123'],
                'payment' => ['amount' => 500],
            ]));

        $refund = $this->apiWithoutCache->refund('purchase_123', 500);

        expect($refund)->toBeInstanceOf(PaymentData::class);
        expect($refund->getPaymentId())->toBe('payment_refund_123');
        expect($refund->getRelatedPurchaseId())->toBe('purchase_123');
        expect($refund->getAmountInCents())->toBe(500);
    });

    it('returns PurchaseData for typed purchase refund responses', function (): void {
        $response = chipPurchaseResponse(['type' => 'purchase']);
        unset($response['purchase']);

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/refund/', ['amount' => 500])
            ->andReturn($response);

        $refund = $this->apiWithoutCache->refund('purchase_123', 500);

        expect($refund)->toBeInstanceOf(PurchaseData::class);
    });

    it('returns PurchaseData for untyped purchase-keyed refund responses', function (): void {
        $response = chipPurchaseResponse();
        unset($response['type']);

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/refund/', ['amount' => 500])
            ->andReturn($response);

        $refund = $this->apiWithoutCache->refund('purchase_123', 500);

        expect($refund)->toBeInstanceOf(PurchaseData::class);
        expect($refund->id)->toBe('purchase_123');
    });

    it('rejects refund responses with no purchase key or known type', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/refund/', ['amount' => 500])
            ->andReturn(['id' => 'unknown_1', 'status' => 'created']);

        expect(fn () => $this->apiWithoutCache->refund('purchase_123', 500))
            ->toThrow(ChipValidationException::class, 'unsupported resource type');
    });

    it('returns PurchaseData for pending refund responses and marks purchases as paid', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/refund/', ['amount' => 500])
            ->andReturn(chipPurchaseResponse(['id' => 'purchase_123', 'status' => 'pending_refund', 'refundable_amount' => 500]));

        $purchase = $this->apiWithoutCache->refund('purchase_123', 500);
        expect($purchase)->toBeInstanceOf(PurchaseData::class);
        expect($purchase->status)->toBe('pending_refund');
        expect($purchase->getRefundableAmountInCents())->toBe(500);

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/mark_as_paid/', ['paid_on' => 1704067200])
            ->andReturn(chipPurchaseResponse(['status' => 'paid']));

        $marked = $this->apiWithoutCache->markAsPaid('purchase_123', 1704067200);
        expect($marked->status)->toBe('paid');
    });
});

describe('PurchasesApi E4 shared validation', function (): void {
    it('accepts numeric tax forms on the raw path', function (): void {
        $this->client->shouldReceive('post')
            ->times(3)
            ->andReturn(chipPurchaseResponse());

        foreach (['6', 6, 6.0] as $tax) {
            $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
                'purchase' => ['products' => [['tax_percent' => $tax]]],
            ]));

            expect($purchase)->toBeInstanceOf(PurchaseData::class);
        }
    });

    it('rejects non-numeric, negative, and oversized tax percents', function (): void {
        foreach (['abc', -1, '-1', 101, []] as $tax) {
            expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
                'purchase' => ['products' => [['tax_percent' => $tax]]],
            ])))->toThrow(ChipValidationException::class, 'Product tax percent must be a number between 0 and 100.');
        }
    });

    it('rejects over-length purchase fields', function (): void {
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['notes' => str_repeat('n', 10001)],
        ])))->toThrow(ChipValidationException::class, 'Purchase notes must be a string of at most 10000 characters.');

        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['language' => 'eng'],
        ])))->toThrow(ChipValidationException::class, 'Purchase language must be a string of at most 2 characters.');

        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['email_message' => str_repeat('e', 513)],
        ])))->toThrow(ChipValidationException::class, 'Purchase email message must be a string of at most 512 characters.');
    });

    it('rejects over-length product fields', function (): void {
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['products' => [['name' => str_repeat('n', 257)]]],
        ])))->toThrow(ChipValidationException::class, 'Product name must be a string of at most 256 characters.');

        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['products' => [['category' => str_repeat('c', 257)]]],
        ])))->toThrow(ChipValidationException::class, 'Product category must be a string of at most 256 characters.');
    });

    it('rejects over-length client fields', function (): void {
        $cases = [
            'legal_name' => 1000,
            'brand_name' => 128,
            'registration_number' => 32,
            'tax_number' => 32,
            'bank_account' => 64,
            'bank_code' => 32,
        ];

        foreach ($cases as $field => $max) {
            expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
                'client' => [$field => str_repeat('x', $max + 1)],
            ])))->toThrow(ChipValidationException::class, 'must be a string of at most');
        }
    });

    it('rejects over-length and non-array cc/bcc entries', function (): void {
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'client' => ['cc' => [str_repeat('a', 250) . '@b.co']],
        ])))->toThrow(ChipValidationException::class, 'Client cc entries must be a string of at most 254 characters.');

        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'client' => ['bcc' => 'not-an-array'],
        ])))->toThrow(ChipValidationException::class, 'Client bcc must be an array of email addresses.');
    });

    it('rejects null, blank, and unknown platforms', function (): void {
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload(['platform' => null])))
            ->toThrow(ChipValidationException::class, 'Platform cannot be null.');
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload(['platform' => ''])))
            ->toThrow(ChipValidationException::class, 'Platform must be one of: web, api, ios, android, macos, windows.');
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload(['platform' => 'beos'])))
            ->toThrow(ChipValidationException::class, 'Platform must be one of: web, api, ios, android, macos, windows.');
    });

    it('rejects null and over-length creator agents but allows empty', function (): void {
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload(['creator_agent' => null])))
            ->toThrow(ChipValidationException::class, 'Creator agent cannot be null.');
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload(['creator_agent' => str_repeat('c', 33)])))
            ->toThrow(ChipValidationException::class, 'Creator agent must be a string of at most 32 characters.');

        $this->client->shouldReceive('post')->once()->andReturn(chipPurchaseResponse());

        $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
            'platform' => 'web',
            'creator_agent' => '',
        ]));

        expect($purchase)->toBeInstanceOf(PurchaseData::class);
    });

    it('accepts boundary-valid lengths across the payload', function (): void {
        $this->client->shouldReceive('post')->once()->andReturn(chipPurchaseResponse());

        $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
            'client' => [
                'legal_name' => str_repeat('l', 1000),
                'cc' => [str_repeat('a', 249) . '@b.co'],
            ],
            'purchase' => [
                'notes' => str_repeat('n', 10000),
                'language' => 'en',
                'products' => [[
                    'name' => str_repeat('n', 256),
                    'category' => str_repeat('c', 256),
                    'tax_percent' => '100',
                ]],
            ],
        ]));

        expect($purchase)->toBeInstanceOf(PurchaseData::class);
    });

    it('rejects an empty platform from config on the checkout path', function (): void {
        config(['chip.defaults.platform' => '']);

        $this->client->shouldReceive('getBrandId')->andReturn('brand_123');

        $client = ClientDetailsData::from(['email' => 'buyer@example.com']);
        $products = [ProductData::from(['name' => 'MYR item', 'price' => 1000, 'currency' => 'MYR'])];

        expect(fn () => $this->apiWithoutCache->createCheckoutPurchase($products, $client))
            ->toThrow(ChipValidationException::class, 'Platform must be one of: web, api, ios, android, macos, windows.');
    });

    it('accepts fractional quantities on the raw path', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->andReturn(chipPurchaseResponse());

        $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['products' => [['name' => 'Frac', 'price' => 100, 'quantity' => '1.5000']]],
        ]));

        expect($purchase->id)->toBe('purchase_123');
    });

    it('rounds fractional line totals half-up in subtotal reconciliation', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->andReturn(chipPurchaseResponse());

        $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => [
                'products' => [
                    ['name' => 'A', 'price' => 100, 'quantity' => '1.555'],
                    ['name' => 'B', 'price' => 100, 'quantity' => '1.555'],
                ],
                'subtotal_override' => 312,
            ],
        ]));

        expect($purchase->id)->toBe('purchase_123');
    });

    it('accepts a zero-quantity line on the raw path', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->andReturn(chipPurchaseResponse());

        $purchase = $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['products' => [
                ['name' => 'Zero', 'price' => 100, 'quantity' => 0],
                ['name' => 'Normal', 'price' => 100, 'quantity' => 1],
            ]],
        ]));

        expect($purchase->id)->toBe('purchase_123');
    });

    it('rejects non-numeric and negative quantities on the raw path', function (): void {
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['products' => [['name' => 'P', 'price' => 100, 'quantity' => -1]]],
        ])))->toThrow(ChipValidationException::class, 'zero or greater');
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['products' => [['name' => 'P', 'price' => 100, 'quantity' => 'abc']]],
        ])))->toThrow(ChipValidationException::class, 'must be numeric');
    });

    it('rejects quantities with more than 4 decimal places on the raw path', function (): void {
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['products' => [['name' => 'P', 'price' => 100, 'quantity' => '1.55555']]],
        ])))->toThrow(ChipValidationException::class, 'at most 4 decimal places');
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['products' => [['name' => 'P', 'price' => 100, 'quantity' => '1.0000000000000001']]],
        ])))->toThrow(ChipValidationException::class, 'at most 4 decimal places');
    });

    it('rejects out-of-range quantities on the raw path', function (): void {
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['products' => [['name' => 'P', 'price' => 100, 'quantity' => 1e30]]],
        ])))->toThrow(ChipValidationException::class, 'out of range');
        expect(fn () => $this->apiWithoutCache->create(chipValidCreatePayload([
            'purchase' => ['products' => [['name' => 'P', 'price' => 100, 'quantity' => '9007199254740993']]],
        ])))->toThrow(ChipValidationException::class, 'out of range');
    });
});
