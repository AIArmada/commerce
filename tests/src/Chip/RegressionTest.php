<?php

declare(strict_types=1);

use AIArmada\Chip\Actions\Purchases\SyncPurchaseRefundState;
use AIArmada\Chip\Actions\SyncChipRecordsFromApiAction;
use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\ProductData;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\SendWebhookReceived;
use AIArmada\Chip\Exceptions\ChipRateLimitException;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Http\Controllers\SendWebhookController;
use AIArmada\Chip\Http\Controllers\WebhookController;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use AIArmada\Chip\Listeners\StoreWebhookData;
use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Models\ChipCustomerLink;
use AIArmada\Chip\Models\Client;
use AIArmada\Chip\Models\CompanyStatement;
use AIArmada\Chip\Models\Payment;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Models\SendLimit;
use AIArmada\Chip\Models\SendWebhook;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Services\ChipCustomerDirectory;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\Chip\Services\Collect\ClientsApi;
use AIArmada\Chip\Services\Collect\PurchasesApi;
use AIArmada\Chip\Services\LocalAnalyticsService;
use AIArmada\Chip\Support\PurchaseIdempotencyLedger;
use AIArmada\Chip\Webhooks\Handlers\PurchasePaidHandler;
use AIArmada\Chip\Webhooks\ProcessChipWebhook;
use AIArmada\Chip\Webhooks\WebhookMonitor;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use Akaunting\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Spatie\WebhookClient\Models\WebhookCall;

function regressionPurchase(array $attributes = []): Purchase
{
    $purchase = new Purchase;
    $purchase->forceFill(array_merge([
        'id' => (string) Str::uuid(),
        'type' => 'purchase',
        'brand_id' => (string) Str::uuid(),
        'created_on' => time(),
        'updated_on' => time(),
        'status' => 'paid',
        'client' => ['email' => 'repair@example.com'],
        'purchase' => ['total' => 10000, 'currency' => 'MYR'],
        'issuer_details' => [],
        'transaction_data' => [],
        'status_history' => [],
        'send_receipt' => false,
        'is_test' => true,
        'is_recurring_token' => false,
        'skip_capture' => false,
        'force_recurring' => false,
        'refund_availability' => 'all',
        'refundable_amount' => 10000,
        'platform' => 'api',
        'product' => 'purchases',
    ], $attributes));
    $purchase->save();

    return $purchase;
}

function regressionUser(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => Str::slug($name) . '@example.com',
        'password' => 'secret',
    ]);
}

function regressionPurchaseResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'purchase_repair_1',
        'created_on' => 1704067200,
        'updated_on' => 1704070800,
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
        'status' => 'paid',
        'status_history' => [],
        'company_id' => 'company_123',
        'is_test' => true,
        'refund_availability' => 'all',
        'refundable_amount' => 1000,
        'payment_method_whitelist' => [],
    ], $overrides);
}

describe('repeat mutations reach the gateway', function (): void {
    it('posts every same-amount refund instead of replaying a stale cache entry', function (): void {
        $client = Mockery::mock(ChipCollectClient::class);
        $posts = 0;

        $client->shouldReceive('post')
            ->zeroOrMoreTimes()
            ->with('purchases/purchase_repair_1/refund/', ['amount' => 500])
            ->andReturnUsing(function () use (&$posts): array {
                $posts++;

                return [
                    'id' => 'payment_refund_' . $posts,
                    'type' => 'payment',
                    'payment' => [
                        'amount' => 500,
                        'currency' => 'MYR',
                        'payment_type' => 'refund',
                    ],
                    'related_to' => ['type' => 'purchase', 'id' => 'purchase_repair_1'],
                ];
            });

        $api = new PurchasesApi(Cache::store('array'), $client);

        $api->refund('purchase_repair_1', 500);
        $api->refund('purchase_repair_1', 500);

        expect($posts)->toBe(2);
    });

    it('posts every same-amount capture instead of replaying a stale cache entry', function (): void {
        $client = Mockery::mock(ChipCollectClient::class);
        $posts = 0;

        $client->shouldReceive('post')
            ->zeroOrMoreTimes()
            ->with('purchases/purchase_repair_1/capture/', ['amount' => 250])
            ->andReturnUsing(function () use (&$posts): array {
                $posts++;

                return regressionPurchaseResponse();
            });

        $api = new PurchasesApi(Cache::store('array'), $client);

        $api->capture('purchase_repair_1', 250);
        $api->capture('purchase_repair_1', 250);

        expect($posts)->toBe(2);
    });

    it('still replays naturally idempotent mutations from cache', function (): void {
        $client = Mockery::mock(ChipCollectClient::class);
        $posts = 0;

        $client->shouldReceive('post')
            ->zeroOrMoreTimes()
            ->with('purchases/purchase_repair_1/cancel/')
            ->andReturnUsing(function () use (&$posts): array {
                $posts++;

                return regressionPurchaseResponse(['status' => 'cancelled']);
            });

        $api = new PurchasesApi(Cache::store('array'), $client);

        $api->cancel('purchase_repair_1');
        $api->cancel('purchase_repair_1');

        expect($posts)->toBe(1);
    });
});

describe('signature verified before owner resolution', function (): void {
    it('returns 401 for a bad signature without resolving the brand owner', function (): void {
        Event::fake();
        config([
            'chip.webhooks.store_webhooks' => false,
            'chip.webhooks.verify_signature' => true,
            'chip.webhooks.collect.webhook_keys' => [],
            'chip.owner.enabled' => true,
            'chip.owner.webhook_brand_id_map' => [],
            'queue.default' => 'sync',
        ]);

        $request = Request::create(
            uri: '/chip/webhooks',
            method: 'POST',
            parameters: ['brand_id' => 'brand-unknown', 'event_type' => 'purchase.paid'],
            server: ['HTTP_X_SIGNATURE' => 'not-valid-base64!!!'],
        );

        $response = app(WebhookController::class)->handle($request);

        expect($response->getStatusCode())->toBe(401);
    });
});

describe('idempotency reservations expire', function (): void {
    it('releases an expired reservation instead of bricking the key', function (): void {
        $ledger = new PurchaseIdempotencyLedger;
        $fingerprint = hash('sha256', 'payload');

        $stub = $ledger->reserve('brand-1', 'repair-key-1', $fingerprint, [
            'client' => ['email' => 'a@example.com'],
            'purchase' => ['total' => 100],
        ]);

        $metadata = $stub->metadata;
        $metadata['chip_idempotency']['reserved_at'] = time() - 90000;
        $stub->forceFill(['metadata' => $metadata])->save();

        expect($ledger->find('brand-1', 'repair-key-1', $fingerprint))->toBeNull();

        $fresh = $ledger->reserve('brand-1', 'repair-key-1', $fingerprint, [
            'client' => ['email' => 'a@example.com'],
            'purchase' => ['total' => 100],
        ]);

        expect($fresh->exists)->toBeTrue();
    });

    it('prunes only expired stubs', function (): void {
        $ledger = new PurchaseIdempotencyLedger;

        $expired = $ledger->reserve('brand-1', 'repair-prune-old', hash('sha256', 'a'), []);
        $metadata = $expired->metadata;
        $metadata['chip_idempotency']['reserved_at'] = time() - 90000;
        $expired->forceFill(['metadata' => $metadata])->save();

        $fresh = $ledger->reserve('brand-1', 'repair-prune-new', hash('sha256', 'b'), []);

        $this->artisan('chip:prune-idempotency-stubs')->assertExitCode(0);

        expect(Purchase::query()->whereKey($expired->getKey())->exists())->toBeFalse()
            ->and(Purchase::query()->whereKey($fresh->getKey())->exists())->toBeTrue();
    });

    it('excludes stubs from transaction metrics', function (): void {
        regressionPurchase(['status' => 'paid', 'created_at' => CarbonImmutable::now()]);

        (new PurchaseIdempotencyLedger)->reserve('brand-1', 'repair-metric-stub', hash('sha256', 'c'), []);

        $metrics = (new LocalAnalyticsService)->getTransactionMetrics(
            CarbonImmutable::now()->subDay(),
            CarbonImmutable::now()->addDay(),
        );

        expect($metrics->total)->toBe(1)
            ->and($metrics->pending)->toBe(0);
    });
});

describe('sync runs under explicit context when scoped', function (): void {
    it('records per-id failures instead of fataling without a console owner', function (): void {
        config()->set('chip.owner.enabled', true);
        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

        Chip::shouldReceive('getPurchase')->andThrow(new Exception('api down'));

        $action = new SyncChipRecordsFromApiAction(new StoreWebhookData);

        $summary = $action->handle(['purchase-missing-1']);

        expect($summary['processed'])->toBe(1)
            ->and($summary['failed'])->toBe(1);
    });

    it('rejects an unresolvable sync owner', function (): void {
        config()->set('chip.owner.enabled', true);

        $this->artisan('chip:sync-from-api', [
            '--purchase-id' => ['purchase-1'],
            '--owner-type' => User::class,
            '--owner-id' => '00000000-0000-0000-0000-000000000000',
        ])->assertExitCode(1);
    });
});

describe('failed deliveries can be reclaimed', function (): void {
    it('re-dispatches after a failed attempt releases its idempotency key', function (): void {
        Event::fake();
        config([
            'chip.webhooks.store_webhooks' => true,
            'chip.webhooks.deduplication' => true,
            'queue.default' => 'sync',
        ]);

        $purchase = regressionPurchase(['status' => 'pending_execute']);

        $payload = [
            'event_type' => 'purchase.paid',
            'id' => $purchase->id,
            'type' => 'purchase',
            'status' => 'paid',
            'brand_id' => 'brand-123',
            'is_test' => true,
            'created_on' => time(),
            'updated_on' => time(),
            'purchase' => [
                'currency' => 'MYR',
                'total' => 10000,
                'products' => [['name' => 'Item', 'price' => 10000, 'quantity' => 1]],
            ],
            'client' => ['email' => 'test@example.com'],
            'issuer_details' => [],
            'transaction_data' => ['payment_method' => 'fpx', 'attempts' => []],
            'status_history' => [],
            'refund_availability' => 'all',
            'refundable_amount' => 10000,
        ];

        $first = WebhookCall::create([
            'name' => Webhook::WEBHOOK_NAME,
            'url' => 'https://example.test/chip/webhooks',
            'payload' => $payload,
        ]);

        (new ProcessChipWebhook($first))->handle();

        $stored = Webhook::query()->withoutOwnerScope()->whereKey($first->getKey())->first();
        expect($stored)->not->toBeNull()
            ->and($stored->idempotency_key)->not->toBeNull();

        // Simulate a failed attempt whose provider claim already lapsed: the
        // redelivery must steal the idempotency key and dispatch again.
        $stored->forceFill([
            'status' => 'failed',
            'processed' => false,
            'processed_at' => null,
            'event_id' => null,
        ])->save();

        $second = WebhookCall::create([
            'name' => Webhook::WEBHOOK_NAME,
            'url' => 'https://example.test/chip/webhooks',
            'payload' => $payload,
        ]);

        (new ProcessChipWebhook($second))->handle();

        expect($stored->refresh()->idempotency_key)->toBeNull()
            ->and($second->refresh()->processed_at)->not->toBeNull()
            ->and(Webhook::query()->withoutOwnerScope()->whereKey($second->getKey())->first()?->processed)->toBeTrue();
    });
});

describe('bounded webhook analytics', function (): void {
    it('computes health from SQL aggregates', function (): void {
        foreach ([
            ['status' => 'processed', 'processed' => true, 'processing_time_ms' => 10.0],
            ['status' => 'processed', 'processed' => true, 'processing_time_ms' => 30.0],
            ['status' => 'failed', 'processed' => false],
            ['status' => 'pending', 'processed' => false],
        ] as $attributes) {
            $row = new Webhook;
            $row->forceFill(array_merge([
                'name' => Webhook::WEBHOOK_NAME,
                'url' => 'https://example.test/chip/webhooks',
                'event_type' => 'purchase.paid',
                'payload' => [],
                'created_at' => CarbonImmutable::now(),
            ], $attributes))->save();
        }

        $health = (new WebhookMonitor)->getHealth(CarbonImmutable::now()->subDay());

        expect($health->total)->toBe(4)
            ->and($health->processed)->toBe(2)
            ->and($health->failed)->toBe(1)
            ->and($health->pending)->toBe(1)
            ->and($health->avgProcessingTimeMs)->toBe(20.0);
    });

    it('returns only backoff-eligible webhooks', function (): void {
        $make = function (int $retryCount, ?CarbonImmutable $lastRetryAt): Webhook {
            $row = new Webhook;
            $row->forceFill([
                'name' => Webhook::WEBHOOK_NAME,
                'url' => 'https://example.test/chip/webhooks',
                'event_type' => 'purchase.paid',
                'payload' => [],
                'status' => 'failed',
                'retry_count' => $retryCount,
                'last_retry_at' => $lastRetryAt,
                'created_at' => CarbonImmutable::now(),
            ])->save();

            return $row;
        };

        $neverRetried = $make(0, null);
        $coolingDown = $make(0, CarbonImmutable::now());
        $eligible = $make(1, CarbonImmutable::now()->subMinutes(10));

        $retryable = app(WebhookRetryManager::class)->getRetryableWebhooks();

        expect($retryable->modelKeys())->toContain($neverRetried->getKey(), $eligible->getKey())
            ->and($retryable->modelKeys())->not->toContain($coolingDown->getKey());
    });
});

describe('refund sync accumulates without double counting', function (): void {
    it('ignores an already stored refund payment on re-sync', function (): void {
        $purchase = regressionPurchase(['purchase' => ['total' => 10000, 'currency' => 'MYR']]);

        $refund = PaymentData::from([
            'id' => 'payment-repair-refund-1',
            'type' => 'payment',
            'related_to' => ['type' => 'purchase', 'id' => $purchase->id],
            'payment' => [
                'amount' => 3000,
                'currency' => 'MYR',
                'payment_type' => 'refund',
            ],
        ]);

        $payment = new Payment;
        $payment->forceFill([
            'id' => 'payment-repair-refund-1',
            'purchase_id' => $purchase->id,
            'payment_type' => 'refund',
            'is_outgoing' => true,
            'amount' => 3000,
            'currency' => 'MYR',
            'net_amount' => 3000,
            'fee_amount' => 0,
            'created_on' => time(),
            'updated_on' => time(),
        ])->save();

        $result = app(SyncPurchaseRefundState::class)->handle($refund, $purchase);

        expect($result?->refund_amount_minor)->toBe(3000)
            ->and($result?->refundable_amount)->toBe(7000);
    });
});

describe('Send webhooks carry owner context', function (): void {
    beforeEach(function (): void {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);

        $this->regressionSendPrivateKey = $privateKey;

        config()->set('chip.webhooks.verify_signature', true);
        config()->set('chip.webhooks.send.webhook_id', null);
        config()->set('chip.webhooks.send.webhook_keys', [$details['key']]);
        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));
    });

    it('dispatches Send webhooks inside the configured owner context', function (): void {
        $owner = regressionUser('Send Owner');
        config()->set('chip.owner.enabled', true);
        config()->set('chip.owner.send_webhook_owner', [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ]);

        $seen = null;
        Event::listen(SendWebhookReceived::class, function () use (&$seen): void {
            $seen = OwnerContext::resolve();
        });

        $payload = json_encode(['id' => 17, 'state' => 'completed'], JSON_THROW_ON_ERROR);
        openssl_sign($payload, $signature, $this->regressionSendPrivateKey, OPENSSL_ALGO_SHA512);

        $response = app(SendWebhookController::class)->handle(
            Request::create(
                uri: '/chip/send/webhooks',
                method: 'POST',
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_SIGNATURE' => base64_encode($signature),
                ],
                content: $payload,
            )
        );

        expect($response->getStatusCode())->toBe(200)
            ->and($seen)->toBeInstanceOf(User::class)
            ->and((string) $seen?->getKey())->toBe((string) $owner->getKey());
    });

    it('fails closed when owner mode is on but no Send owner is configured', function (): void {
        config()->set('chip.owner.enabled', true);
        config()->set('chip.owner.send_webhook_owner', []);

        $payload = json_encode(['id' => 18, 'state' => 'completed'], JSON_THROW_ON_ERROR);
        openssl_sign($payload, $signature, $this->regressionSendPrivateKey, OPENSSL_ALGO_SHA512);

        $response = app(SendWebhookController::class)->handle(
            Request::create(
                uri: '/chip/send/webhooks',
                method: 'POST',
                server: [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_X_SIGNATURE' => base64_encode($signature),
                ],
                content: $payload,
            )
        );

        expect($response->getStatusCode())->toBe(500);
    });
});

describe('signature middleware alias', function (): void {
    it('exposes the Collect signature middleware to host routes', function (): void {
        expect(app('router')->getMiddleware()['chip.verify-webhook'] ?? null)
            ->toBe(VerifyWebhookSignature::class);
    });
});

describe('webhook throttle default', function (): void {
    it('ships an explicit throttle on the webhook middleware stack', function (): void {
        expect(config('chip.webhooks.middleware'))->toContain('throttle:120,1');
    });
});

describe('explicit mass assignment', function (): void {
    it('keeps owner columns out of mass assignment on every model', function (): void {
        foreach ([
            new BankAccount,
            new Client,
            new CompanyStatement,
            new SendInstruction,
            new SendLimit,
            new SendWebhook,
            new ChipCustomerLink,
        ] as $model) {
            expect($model->isFillable('owner_type'))->toBeFalse('owner_type fillable on ' . $model::class)
                ->and($model->isFillable('owner_id'))->toBeFalse('owner_id fillable on ' . $model::class);
        }

        expect((new SendInstruction)->isFillable('amount'))->toBeTrue()
            ->and((new SendInstruction)->isFillable('state'))->toBeTrue()
            ->and((new Client)->isFillable('email'))->toBeTrue()
            ->and((new BankAccount)->isFillable('status'))->toBeTrue();
    });
});

describe('wide timestamp and money columns', function (): void {
    it('round-trips post-2038 timestamps and large minor-unit totals', function (): void {
        $purchase = regressionPurchase([
            'created_on' => 4102444800,
            'total_minor' => 3000000000,
            'refund_amount_minor' => 2500000000,
            'refundable_amount' => 500000000,
        ]);

        $purchase->refresh();
        $raw = $purchase->getAttributes();

        expect($raw['created_on'])->toBe(4102444800)
            ->and($purchase->total_minor)->toBe(3000000000)
            ->and($purchase->refund_amount_minor)->toBe(2500000000)
            ->and($purchase->refundable_amount)->toBe(500000000);
    });
});

describe('public key fetch uses the shared pipeline', function (): void {
    it('rate-limits public key fetches like any other API call', function (): void {
        RateLimiter::clear('chip_api:' . ChipCollectClient::class);
        config()->set('chip.http.rate_limit.max_attempts', 1);
        config()->set('chip.http.rate_limit.decay_seconds', 60);

        Http::fake(['*' => Http::response(json_encode('test-pem-key'), 200)]);

        $client = new ChipCollectClient('key', 'brand', 'https://example.test/', 5, []);

        try {
            expect($client->get('public_key/'))->toBe('test-pem-key');

            $client->get('public_key/');

            $this->fail('Second public key fetch was not rate-limited.');
        } catch (ChipRateLimitException) {
            expect(true)->toBeTrue();
        } finally {
            RateLimiter::clear('chip_api:' . ChipCollectClient::class);
            config()->set('chip.http.rate_limit.max_attempts', 60);
        }
    });
});

describe('per-owner rate limit keys', function (): void {
    it('scopes rate limit buckets by owner when owner mode is on', function (): void {
        config()->set('chip.owner.enabled', true);

        $ownerA = regressionUser('Rate Owner A');
        $ownerB = regressionUser('Rate Owner B');

        $client = new ChipCollectClient('key', 'brand', 'https://example.test/', 5, []);
        $method = new ReflectionMethod(ChipCollectClient::class, 'rateLimitKey');

        $keyA = OwnerContext::withOwner($ownerA, fn (): string => $method->invoke($client));
        $keyB = OwnerContext::withOwner($ownerB, fn (): string => $method->invoke($client));

        expect($keyA)->not->toBe($keyB);

        config()->set('chip.owner.enabled', false);
    });
});

describe('robustness nits', function (): void {
    it('tolerates a malformed webhook event timestamp', function (): void {
        $enriched = EnrichedWebhookPayload::fromPayload('purchase.paid', [
            'id' => 'purchase-repair-ts',
            'type' => 'purchase',
            'created_on' => 'not-a-date-at-all-!!!',
        ]);

        expect($enriched->eventTimestamp)->toBeNull();
    });

    it('rejects outbound resource ids that could escape the endpoint', function (): void {
        $client = Mockery::mock(ChipCollectClient::class);
        $api = new PurchasesApi(null, $client);

        expect(fn () => $api->find('../../admin'))->toThrow(ChipValidationException::class);

        $clients = new ClientsApi($client);

        expect(fn () => $clients->delete('x/y'))->toThrow(ChipValidationException::class);
    });

    it('validates bank account input like send instructions', function (): void {
        $client = Mockery::mock(ChipSendClient::class);
        $service = new ChipSendService($client);

        expect(fn () => $service->createBankAccount('', '', '', ''))
            ->toThrow(ChipValidationException::class);
    });
});

describe('half-up minor-unit math', function (): void {
    it('rounds fractional quantities half-up instead of truncating', function (): void {
        $product = ProductData::make(
            'Fractional',
            Money::MYR(1),
            '0.5',
        );

        expect($product->getSubtotalInCents())->toBe(1)
            ->and($product->getTotalPriceInCents())->toBe(1);
    });

    it('rounds net line totals once instead of per leg', function (): void {
        $product = ProductData::make(
            'Net',
            Money::MYR(199),
            '1.5',
            Money::MYR(100),
        );

        expect($product->getTotalPriceInCents())->toBe(149);
    });

    it('rejects non-numeric quantities', function (): void {
        $product = ProductData::make('Bad', Money::MYR(100), 'lots');

        expect(fn () => $product->getSubtotalInCents())->toThrow(InvalidArgumentException::class);
    });
});

describe('paid handler refreshes denormalized columns', function (): void {
    it('stores total, method, and updated_on from the paid payload', function (): void {
        Event::fake();

        $purchase = regressionPurchase([
            'status' => 'pending_execute',
            'purchase' => ['total' => 10000, 'currency' => 'MYR'],
        ]);

        $enriched = EnrichedWebhookPayload::fromPayload('purchase.paid', [
            'id' => $purchase->id,
            'type' => 'purchase',
            'status' => 'paid',
            'brand_id' => 'brand-123',
            'created_on' => time(),
            'updated_on' => 1704070800,
            'client' => ['email' => 'test@example.com'],
            'purchase' => [
                'currency' => 'MYR',
                'total' => 10000,
                'products' => [['name' => 'Item', 'price' => 10000, 'quantity' => 1]],
            ],
            'issuer_details' => [],
            'transaction_data' => ['payment_method' => 'fpx', 'attempts' => []],
            'status_history' => [],
            'is_test' => true,
            'refund_availability' => 'all',
            'refundable_amount' => 10000,
        ]);

        $result = app(PurchasePaidHandler::class)->handle($enriched);

        expect($result->isHandled())->toBeTrue();

        $purchase->refresh();

        expect($purchase->status)->toBe('paid')
            ->and($purchase->total_minor)->toBe(10000)
            ->and($purchase->payment_method)->toBe('fpx')
            ->and($purchase->getAttributes()['updated_on'])->toBe(1704070800);

        Event::assertDispatched(PurchasePaid::class);
    });
});

describe('directory lookup scopes the ambient owner', function (): void {
    it('resolves the ambient owner explicitly when no owner is passed', function (): void {
        config()->set('chip.owner.enabled', true);
        config()->set('chip.owner.include_global', false);

        $ownerOne = regressionUser('Directory Owner One');
        $ownerTwo = regressionUser('Directory Owner Two');
        $subjectOne = regressionUser('Directory Subject One');
        $subjectTwo = regressionUser('Directory Subject Two');

        /** @var ChipCustomerDirectory $directory */
        $directory = app(ChipCustomerDirectoryInterface::class);

        OwnerContext::withOwner($ownerOne, fn () => $directory->link($subjectOne, 'chip_customer_repair_1'));
        OwnerContext::withOwner($ownerTwo, fn () => $directory->link($subjectTwo, 'chip_customer_repair_2'));

        $found = OwnerContext::withOwner($ownerOne, fn () => $directory->findByChipCustomerId('chip_customer_repair_1'));
        $hidden = OwnerContext::withOwner($ownerOne, fn () => $directory->findByChipCustomerId('chip_customer_repair_2'));

        expect($found?->chip_customer_id)->toBe('chip_customer_repair_1')
            ->and($hidden)->toBeNull();
    });
});
