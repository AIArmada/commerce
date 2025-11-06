<?php

declare(strict_types=1);

use AIArmada\Chip\Builders\PurchaseBuilder;
use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\DataObjects\Client;
use AIArmada\Chip\DataObjects\ClientDetails;
use AIArmada\Chip\DataObjects\Product;
use AIArmada\Chip\DataObjects\Purchase;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\SubscriptionService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

beforeEach(function (): void {
    $this->client = Mockery::mock(ChipCollectClient::class);
    $this->service = new ChipCollectService($this->client);
});

function chipCollectPurchaseResponse(array $overrides = []): array
{
    $base = [
        'id' => 'purchase_123',
        'type' => 'purchase',
        'created_on' => strtotime('2024-01-01T00:00:00Z'),
        'updated_on' => strtotime('2024-01-01T00:05:00Z'),
        'client' => [
            'email' => 'buyer@example.com',
        ],
        'purchase' => [
            'currency' => 'MYR',
            'products' => [[
                'name' => 'Test Product',
                'price' => 1000,
                'quantity' => 1,
            ]],
        ],
        'brand_id' => 'brand_123',
        'issuer_details' => [],
        'transaction_data' => [],
        'status' => 'created',
        'status_history' => [],
        'company_id' => 'company_123',
        'is_test' => true,
        'client_id' => 'client_123',
        'send_receipt' => false,
        'is_recurring_token' => false,
        'recurring_token' => null,
        'skip_capture' => false,
        'force_recurring' => false,
        'reference_generated' => 'REF_123',
        'reference' => null,
        'notes' => null,
        'issued' => null,
        'due' => null,
        'refund_availability' => 'all',
        'refundable_amount' => 1000,
        'payment_method_whitelist' => [],
        'currency_conversion' => null,
        'success_redirect' => null,
        'failure_redirect' => null,
        'cancel_redirect' => null,
        'success_callback' => null,
        'creator_agent' => 'Laravel Package',
        'platform' => 'api',
        'product' => 'purchases',
        'created_from_ip' => null,
        'invoice_url' => null,
        'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase_123',
        'direct_post_url' => null,
        'marked_as_paid' => false,
        'order_id' => null,
    ];

    return array_replace_recursive($base, $overrides);
}

describe('ChipCollectService Public Key', function (): void {
    it('returns the public key as a PEM string', function (): void {
        $pemString = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA7...\n-----END PUBLIC KEY-----\n";
        $this->client->shouldReceive('get')
            ->with('public_key/')
            ->andReturn($pemString);

        $publicKey = $this->service->getPublicKey();

        expect($publicKey)->toBeString();
        expect($publicKey)->toContain('BEGIN PUBLIC KEY');
        expect($publicKey)->toContain('END PUBLIC KEY');
    });
});

describe('ChipCollectService Purchase Management', function (): void {
    it('can create a purchase', function (): void {
        $purchaseData = [
            'id' => 'purchase_123',
            'type' => 'payment',
            'created_on' => '2024-01-01T12:00:00Z',
            'updated_on' => '2024-01-01T12:00:00Z',
            'client' => [
                'id' => 'client_123',
                'email' => 'test@example.com',
            ],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [
                    [
                        'name' => 'Test Product',
                        'price' => 10000,
                        'quantity' => 1,
                    ],
                ],
            ],
            'brand_id' => 'test_brand_id',
            'payment' => null,
            'issuer_details' => [],
            'transaction_data' => [],
            'status' => 'created',
            'status_history' => [],
            'viewed_on' => null,
            'company_id' => 'company_123',
            'is_test' => true,
            'user_id' => null,
            'billing_template_id' => null,
            'client_id' => 'client_123',
            'send_receipt' => true,
            'is_recurring_token' => false,
            'recurring_token' => null,
            'skip_capture' => false,
            'force_recurring' => false,
            'reference_generated' => 'REF_123',
            'reference' => 'ORDER_001',
            'notes' => null,
            'issued' => null,
            'due' => null,
            'refund_availability' => 'full',
            'refundable_amount' => 10000,
            'currency_conversion' => null,
            'payment_method_whitelist' => [],
            'success_redirect' => null,
            'failure_redirect' => null,
            'cancel_redirect' => null,
            'success_callback' => null,
            'creator_agent' => 'Laravel Package',
            'platform' => 'api',
            'product' => 'CHIP',
            'created_from_ip' => null,
            'invoice_url' => null,
            'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase_123',
            'direct_post_url' => null,
            'marked_as_paid' => false,
            'order_id' => null,
        ];

        $requestData = [
            'client' => ['email' => 'test@example.com'],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [
                    ['name' => 'Test Product', 'price' => 10000, 'quantity' => 1],
                ],
            ],
            'brand_id' => 'test_brand_id',
            'reference' => 'ORDER_001',
        ];

        $this->client->shouldReceive('post')
            ->with('purchases/', $requestData)
            ->andReturn($purchaseData);

        $purchase = $this->service->createPurchase($requestData);

        expect($purchase)->toBeInstanceOf(Purchase::class);
        expect($purchase->id)->toBe('purchase_123');
        expect($purchase->status)->toBe('created');
        expect($purchase->reference)->toBe('ORDER_001');
    });

    it('accepts an existing client reference without client payload', function (): void {
        $purchaseData = [
            'id' => 'purchase_456',
            'type' => 'payment',
            'created_on' => '2024-01-01T12:00:00Z',
            'updated_on' => '2024-01-01T12:00:00Z',
            'client' => [
                'id' => 'client_456',
                'email' => 'existing@example.com',
            ],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [
                    [
                        'name' => 'Existing Customer Product',
                        'price' => 20000,
                        'quantity' => 1,
                    ],
                ],
            ],
            'brand_id' => 'test_brand_id',
            'payment' => null,
            'issuer_details' => [],
            'transaction_data' => [],
            'status' => 'created',
            'status_history' => [],
            'viewed_on' => null,
            'company_id' => 'company_456',
            'is_test' => true,
            'client_id' => 'client_456',
            'send_receipt' => false,
            'is_recurring_token' => false,
            'skip_capture' => false,
            'force_recurring' => false,
            'reference_generated' => 'REF_456',
            'refund_availability' => 'all',
            'refundable_amount' => 20000,
            'currency_conversion' => null,
            'payment_method_whitelist' => [],
            'success_redirect' => null,
            'failure_redirect' => null,
            'cancel_redirect' => null,
            'creator_agent' => 'Laravel Package',
            'platform' => 'api',
            'product' => 'CHIP',
            'created_from_ip' => null,
            'invoice_url' => null,
            'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase_456',
            'direct_post_url' => null,
            'marked_as_paid' => false,
            'order_id' => null,
        ];

        $requestData = [
            'client_id' => 'client_456',
            'purchase' => [
                'currency' => 'MYR',
                'products' => [
                    ['name' => 'Existing Customer Product', 'price' => 20000, 'quantity' => 1],
                ],
            ],
            'brand_id' => 'test_brand_id',
        ];

        $this->client->shouldReceive('post')
            ->with('purchases/', $requestData)
            ->andReturn($purchaseData);

        $purchase = $this->service->createPurchase($requestData);

        expect($purchase)->toBeInstanceOf(Purchase::class);
        expect($purchase->client_id)->toBe('client_456');
        expect($purchase->client->email)->toBe('existing@example.com');
    });

    it('can retrieve a purchase', function (): void {
        $purchaseData = [
            'id' => 'purchase_123',
            'type' => 'payment',
            'created_on' => '2024-01-01T12:00:00Z',
            'updated_on' => '2024-01-01T12:00:00Z',
            'client' => [
                'id' => 'client_123',
                'email' => 'test@example.com',
            ],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [
                    [
                        'name' => 'Test Product',
                        'price' => 10000,
                        'quantity' => 1,
                    ],
                ],
            ],
            'brand_id' => 'test_brand_id',
            'payment' => null,
            'issuer_details' => [],
            'transaction_data' => [],
            'status' => 'paid',
            'status_history' => [],
            'viewed_on' => null,
            'company_id' => 'company_123',
            'is_test' => true,
            'user_id' => null,
            'billing_template_id' => null,
            'client_id' => 'client_123',
            'send_receipt' => true,
            'is_recurring_token' => false,
            'recurring_token' => null,
            'skip_capture' => false,
            'force_recurring' => false,
            'reference_generated' => 'REF_123',
            'reference' => 'ORDER_001',
            'notes' => null,
            'issued' => null,
            'due' => null,
            'refund_availability' => 'full',
            'refundable_amount' => 10000,
            'currency_conversion' => null,
            'payment_method_whitelist' => [],
            'success_redirect' => null,
            'failure_redirect' => null,
            'cancel_redirect' => null,
            'success_callback' => null,
            'creator_agent' => 'Laravel Package',
            'platform' => 'api',
            'product' => 'CHIP',
            'created_from_ip' => null,
            'invoice_url' => null,
            'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase_123',
            'direct_post_url' => null,
            'marked_as_paid' => false,
            'order_id' => null,
        ];

        $this->client->shouldReceive('get')
            ->with('purchases/purchase_123/')
            ->andReturn($purchaseData);

        $purchase = $this->service->getPurchase('purchase_123');

        expect($purchase)->toBeInstanceOf(Purchase::class);
        expect($purchase->id)->toBe('purchase_123');
        expect($purchase->status)->toBe('paid');
    });

    it('can cancel a purchase', function (): void {
        $purchaseData = [
            'id' => 'purchase_123',
            'type' => 'payment',
            'created_on' => '2024-01-01T12:00:00Z',
            'updated_on' => '2024-01-01T12:00:00Z',
            'client' => [
                'id' => 'client_123',
                'email' => 'test@example.com',
            ],
            'purchase' => [
                'currency' => 'MYR',
                'products' => [
                    [
                        'name' => 'Test Product',
                        'price' => 10000,
                        'quantity' => 1,
                    ],
                ],
            ],
            'brand_id' => 'test_brand_id',
            'payment' => null,
            'issuer_details' => [],
            'transaction_data' => [],
            'status' => 'cancelled',
            'status_history' => [],
            'viewed_on' => null,
            'company_id' => 'company_123',
            'is_test' => true,
            'user_id' => null,
            'billing_template_id' => null,
            'client_id' => 'client_123',
            'send_receipt' => true,
            'is_recurring_token' => false,
            'recurring_token' => null,
            'skip_capture' => false,
            'force_recurring' => false,
            'reference_generated' => 'REF_123',
            'reference' => 'ORDER_001',
            'notes' => null,
            'issued' => null,
            'due' => null,
            'refund_availability' => 'none',
            'refundable_amount' => 0,
            'currency_conversion' => null,
            'payment_method_whitelist' => [],
            'success_redirect' => null,
            'failure_redirect' => null,
            'cancel_redirect' => null,
            'success_callback' => null,
            'creator_agent' => 'Laravel Package',
            'platform' => 'api',
            'product' => 'CHIP',
            'created_from_ip' => null,
            'invoice_url' => null,
            'checkout_url' => 'https://gate.chip-in.asia/checkout/purchase_123',
            'direct_post_url' => null,
            'marked_as_paid' => false,
            'order_id' => null,
        ];

        $this->client->shouldReceive('post')
            ->with('purchases/purchase_123/cancel/')
            ->andReturn($purchaseData);

        $purchase = $this->service->cancelPurchase('purchase_123');

        expect($purchase)->toBeInstanceOf(Purchase::class);
        expect($purchase->status)->toBe('cancelled');
    });

    it('can get payment methods', function (): void {
        $paymentMethods = [
            'available_payment_methods' => ['fpx', 'visa', 'mastercard'],
            'names' => [
                'fpx' => 'FPX Online Banking',
                'visa' => 'Visa',
                'mastercard' => 'Mastercard',
            ],
        ];

        $this->client->shouldReceive('get')
            ->with('payment_methods/?brand_id=test_brand&currency=MYR')
            ->andReturn($paymentMethods);

        $result = $this->service->getPaymentMethods(['brand_id' => 'test_brand', 'currency' => 'MYR']);

        expect($result)->toBe($paymentMethods);
        expect($result['available_payment_methods'])->toContain('fpx');
    });

    it('automatically uses configured brand_id and currency for payment methods', function (): void {
        config(['chip.defaults.currency' => 'MYR']);

        $paymentMethods = [
            'available_payment_methods' => ['fpx', 'visa'],
        ];

        $this->client->shouldReceive('getBrandId')
            ->andReturn('configured_brand_id');

        $this->client->shouldReceive('get')
            ->with('payment_methods/?brand_id=configured_brand_id&currency=MYR')
            ->andReturn($paymentMethods);

        $result = $this->service->getPaymentMethods();

        expect($result)->toBe($paymentMethods);
        expect($result['available_payment_methods'])->toContain('fpx');
    });

    it('allows overriding brand_id and currency for payment methods', function (): void {
        config(['chip.defaults.currency' => 'MYR']);

        $paymentMethods = [
            'available_payment_methods' => ['visa'],
        ];

        $this->client->shouldReceive('getBrandId')
            ->andReturn('configured_brand_id');

        $this->client->shouldReceive('get')
            ->with('payment_methods/?brand_id=override_brand&currency=SGD')
            ->andReturn($paymentMethods);

        $result = $this->service->getPaymentMethods(['brand_id' => 'override_brand', 'currency' => 'SGD']);

        expect($result)->toBe($paymentMethods);
    });

    it('uses cache repository when available for payment methods', function (): void {
        config(['chip.defaults.currency' => 'MYR']);

        $cache = Mockery::mock(CacheRepository::class);
        $service = new ChipCollectService($this->client, $cache);

        $filters = ['brand_id' => 'test_brand', 'currency' => 'MYR'];
        $paymentMethods = ['available_payment_methods' => ['visa']];

        $this->client->shouldReceive('get')
            ->once()
            ->with('payment_methods/?brand_id=test_brand&currency=MYR')
            ->andReturn($paymentMethods);

        $cache->shouldReceive('remember')
            ->once()
            ->with(
                Mockery::type('string'),
                Mockery::on(function ($ttl) {
                    $expected = config('chip.cache.ttl.payment_methods') ?? config('chip.cache.default_ttl', 3600);

                    return $ttl === $expected;
                }),
                Mockery::type('callable')
            )
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $service->getPaymentMethods($filters);

        expect($result)->toBe($paymentMethods);
    });
});

describe('ChipCollectService Client Management', function (): void {
    it('can create a client', function (): void {
        $clientData = [
            'id' => 'client_123',
            'email' => 'john@example.com',
            'phone' => '+60123456789',
            'full_name' => 'John Doe',
            'personal_code' => null,
            'brand_id' => 'test_brand_id',
            'created_on' => '2024-01-01T12:00:00Z',
            'updated_on' => '2024-01-01T12:00:00Z',
        ];

        $requestData = [
            'email' => 'john@example.com',
            'phone' => '+60123456789',
            'full_name' => 'John Doe',
        ];

        $this->client->shouldReceive('post')
            ->with('clients/', $requestData)
            ->andReturn($clientData);

        $client = $this->service->createClient($requestData);

        expect($client)->toBeInstanceOf(Client::class);
        expect($client->id)->toBe('client_123');
        expect($client->email)->toBe('john@example.com');
        expect($client->full_name)->toBe('John Doe');
    });

    it('can retrieve a client', function (): void {
        $clientData = [
            'id' => 'client_123',
            'email' => 'john@example.com',
            'phone' => '+60123456789',
            'full_name' => 'John Doe',
            'personal_code' => null,
            'brand_id' => 'test_brand_id',
            'created_on' => '2024-01-01T12:00:00Z',
            'updated_on' => '2024-01-01T12:00:00Z',
        ];

        $this->client->shouldReceive('get')
            ->with('clients/client_123/')
            ->andReturn($clientData);

        $client = $this->service->getClient('client_123');

        expect($client)->toBeInstanceOf(Client::class);
        expect($client->id)->toBe('client_123');
        expect($client->full_name)->toBe('John Doe');
    });

    it('can list clients', function (): void {
        $clientsData = [
            [
                'id' => 'client_123',
                'email' => 'john@example.com',
                'full_name' => 'John Doe',
            ],
            [
                'id' => 'client_456',
                'email' => 'jane@example.com',
                'full_name' => 'Jane Smith',
            ],
        ];

        $this->client->shouldReceive('get')
            ->with('clients/')
            ->andReturn($clientsData);

        $clients = $this->service->listClients();

        expect($clients)->toBe($clientsData);
        expect($clients)->toHaveCount(2);
    });
});

describe('ChipCollectService Purchase Actions', function (): void {
    it('can refund a purchase with a specific amount', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/refund/', ['amount' => 500])
            ->andReturn(chipCollectPurchaseResponse(['refundable_amount' => 500]));

        $purchase = $this->service->refundPurchase('purchase_123', 500);

        expect($purchase->refundable_amount)->toBe(500);
    });

    it('can charge a recurring purchase using a token', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/charge/', ['recurring_token' => 'rt_456'])
            ->andReturn(chipCollectPurchaseResponse(['id' => 'purchase_123', 'is_recurring_token' => true]));

        $purchase = $this->service->chargePurchase('purchase_123', 'rt_456');

        expect($purchase->id)->toBe('purchase_123');
        expect($purchase->is_recurring_token)->toBeTrue();
    });

    it('can capture a purchase without specifying an amount', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/capture/', [])
            ->andReturn(chipCollectPurchaseResponse(['status' => 'captured']));

        $purchase = $this->service->capturePurchase('purchase_123');

        expect($purchase->status)->toBe('captured');
    });

    it('can release a purchase', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/release/')
            ->andReturn(chipCollectPurchaseResponse(['status' => 'released']));

        $purchase = $this->service->releasePurchase('purchase_123');

        expect($purchase->status)->toBe('released');
    });

    it('can mark a purchase as paid without timestamp', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/mark_as_paid/', [])
            ->andReturn(chipCollectPurchaseResponse(['status' => 'paid', 'marked_as_paid' => true]));

        $purchase = $this->service->markPurchaseAsPaid('purchase_123');

        expect($purchase->status)->toBe('paid');
        expect($purchase->marked_as_paid)->toBeTrue();
    });

    it('can resend a purchase invoice', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/purchase_123/resend_invoice/')
            ->andReturn(chipCollectPurchaseResponse(['id' => 'purchase_123']));

        $purchase = $this->service->resendInvoice('purchase_123');

        expect($purchase->id)->toBe('purchase_123');
    });

    it('can delete a recurring token for a purchase', function (): void {
        $this->client->shouldReceive('delete')
            ->once()
            ->with('purchases/purchase_123/recurring_token/')
            ->andReturn([]);

        $this->service->deleteRecurringToken('purchase_123');

        expect(true)->toBeTrue();
    });
});

describe('ChipCollectService Account & Reporting', function (): void {
    it('retrieves account balance', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('account/balance/')
            ->andReturn(['balance' => 10000]);

        expect($this->service->getAccountBalance())->toBe(['balance' => 10000]);
    });

    it('retrieves account turnover with filters', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('account/turnover/?date_from=2024-01-01')
            ->andReturn(['turnover' => []]);

        expect($this->service->getAccountTurnover(['date_from' => '2024-01-01']))->toBe(['turnover' => []]);
    });

    it('lists company statements', function (): void {
        $payload = [[
            'id' => 'statement_123',
            'type' => 'statement',
            'format' => 'csv',
            'timezone' => 'Europe/Oslo',
            'is_test' => false,
            'company_uid' => 'company_456',
            'status' => 'queued',
            'created_on' => 1712070000,
            'updated_on' => 1712073600,
        ]];

        $this->client->shouldReceive('get')
            ->once()
            ->with('company_statements/?status=active')
            ->andReturn(['data' => $payload, 'meta' => ['total' => 1]]);

        $statements = $this->service->listCompanyStatements(['status' => 'active']);

        expect($statements)->toHaveKey('data');
        expect($statements['data'][0])->toBeInstanceOf(AIArmada\Chip\DataObjects\CompanyStatement::class);
        expect($statements['data'][0]->status)->toBe('queued');
        expect($statements['meta']['total'])->toBe(1);
    });

    it('retrieves a specific company statement', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('company_statements/statement_123/')
            ->andReturn([
                'id' => 'statement_123',
                'type' => 'statement',
                'format' => 'pdf',
                'timezone' => 'UTC',
                'is_test' => false,
                'company_uid' => 'company_456',
                'status' => 'finished',
                'created_on' => 1712070000,
                'updated_on' => 1712073600,
            ]);

        $statement = $this->service->getCompanyStatement('statement_123');

        expect($statement)->toBeInstanceOf(AIArmada\Chip\DataObjects\CompanyStatement::class);
        expect($statement->status)->toBe('finished');
    });

    it('cancels a company statement', function (): void {
        $this->client->shouldReceive('post')
            ->once()
            ->with('company_statements/statement_123/cancel/')
            ->andReturn([
                'id' => 'statement_123',
                'type' => 'statement',
                'format' => 'csv',
                'timezone' => 'UTC',
                'is_test' => false,
                'company_uid' => 'company_456',
                'status' => 'cancelled',
                'created_on' => 1712070000,
                'updated_on' => 1712073600,
            ]);

        $cancelled = $this->service->cancelCompanyStatement('statement_123');

        expect($cancelled)->toBeInstanceOf(AIArmada\Chip\DataObjects\CompanyStatement::class);
        expect($cancelled->isCancelled())->toBeTrue();
    });
});

describe('ChipCollectService Webhooks', function (): void {
    it('creates a webhook', function (): void {
        $payload = ['url' => 'https://example.com/webhook'];

        $this->client->shouldReceive('post')
            ->once()
            ->with('webhooks/', $payload)
            ->andReturn(['id' => 'wh_123'] + $payload);

        expect($this->service->createWebhook($payload))
            ->toBe(['id' => 'wh_123'] + $payload);
    });

    it('retrieves a webhook', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('webhooks/wh_123/')
            ->andReturn(['id' => 'wh_123']);

        expect($this->service->getWebhook('wh_123'))->toBe(['id' => 'wh_123']);
    });

    it('updates a webhook', function (): void {
        $updatePayload = ['events' => ['purchase.paid']];

        $this->client->shouldReceive('put')
            ->once()
            ->with('webhooks/wh_123/', $updatePayload)
            ->andReturn(['id' => 'wh_123'] + $updatePayload);

        expect($this->service->updateWebhook('wh_123', $updatePayload))
            ->toBe(['id' => 'wh_123'] + $updatePayload);
    });

    it('deletes a webhook', function (): void {
        $this->client->shouldReceive('delete')
            ->once()
            ->with('webhooks/wh_123/')
            ->andReturn([]);

        $this->service->deleteWebhook('wh_123');

        expect(true)->toBeTrue();
    });

    it('lists webhooks with filters', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('webhooks/?status=active')
            ->andReturn(['data' => []]);

        expect($this->service->listWebhooks(['status' => 'active']))
            ->toBe(['data' => []]);
    });
});

describe('ChipCollectService Client Tokens', function (): void {
    it('lists recurring tokens for a client', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('clients/client_123/recurring_tokens/')
            ->andReturn([['id' => 'token_1']]);

        expect($this->service->listClientRecurringTokens('client_123'))
            ->toBe([['id' => 'token_1']]);
    });

    it('retrieves a specific recurring token', function (): void {
        $this->client->shouldReceive('get')
            ->once()
            ->with('clients/client_123/recurring_tokens/token_456/')
            ->andReturn(['id' => 'token_456']);

        expect($this->service->getClientRecurringToken('client_123', 'token_456'))
            ->toBe(['id' => 'token_456']);
    });

    it('deletes a recurring token', function (): void {
        $this->client->shouldReceive('delete')
            ->once()
            ->with('clients/client_123/recurring_tokens/token_456/')
            ->andReturn([]);

        $this->service->deleteClientRecurringToken('client_123', 'token_456');

        expect(true)->toBeTrue();
    });
});

describe('ChipCollectService Utilities', function (): void {
    it('provides a purchase builder instance', function (): void {
        expect($this->service->purchase())->toBeInstanceOf(PurchaseBuilder::class);
    });

    it('returns the configured brand id from the client', function (): void {
        $this->client->shouldReceive('getBrandId')
            ->once()
            ->andReturn('brand_from_service');

        expect($this->service->getBrandId())->toBe('brand_from_service');
    });

    it('creates checkout purchases via the service facade', function (): void {
        $clientDetails = ClientDetails::fromArray(['email' => 'checkout@example.com']);
        $products = [new Product('Subscription', '1', 5000, 0, 0.0, null)];

        $this->client->shouldReceive('getBrandId')
            ->andReturn('brand_checkout');

        $this->client->shouldReceive('post')
            ->once()
            ->with('purchases/', Mockery::on(function (array $payload) {
                return $payload['brand_id'] === 'brand_checkout'
                    && $payload['client']['email'] === 'checkout@example.com'
                    && $payload['purchase']['products'][0]['name'] === 'Subscription';
            }))
            ->andReturn(chipCollectPurchaseResponse(['brand_id' => 'brand_checkout']));

        $purchase = $this->service->createCheckoutPurchase($products, $clientDetails, []);

        expect($purchase->brand_id)->toBe('brand_checkout');
    });

    it('reuses the same subscription service instance', function (): void {
        $first = $this->service->subscriptions();
        $second = $this->service->subscriptions();

        expect($first)->toBeInstanceOf(SubscriptionService::class);
        expect($second)->toBe($first);
    });
});
