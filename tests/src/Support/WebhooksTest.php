<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Webhooks\CommerceSignatureValidator;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookConfig;

beforeEach(function (): void {
    SupportWebhookProcessor::reset();

    Schema::dropIfExists('webhook_calls');
    Schema::create('webhook_calls', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->text('url')->nullable();
        $table->json('headers')->nullable();
        $table->json('payload')->nullable();
        $table->json('exception')->nullable();
        $table->timestamp('processed_at')->nullable();
        $table->timestamps();
    });
});

it('validates commerce webhook signatures with constant time comparison', function (): void {
    $payload = json_encode(['event' => 'payment.completed'], JSON_THROW_ON_ERROR);
    $secret = 'super-secret';
    $request = Request::create('/webhooks/test', 'POST', [], [], [], [], $payload);
    $request->headers->set('X-Commerce-Signature', hash_hmac('sha256', $payload, $secret));

    $validator = new SupportWebhookSignatureValidator;

    expect($validator->isValid($request, supportWebhookConfig($secret)))->toBeTrue();
});

it('rejects unsigned invalid or unconfigured commerce webhook signatures', function (): void {
    $payload = json_encode(['event' => 'payment.completed'], JSON_THROW_ON_ERROR);
    $validator = new SupportWebhookSignatureValidator;
    $request = Request::create('/webhooks/test', 'POST', [], [], [], [], $payload);

    expect($validator->isValid($request, supportWebhookConfig('super-secret')))->toBeFalse();

    $request->headers->set('X-Commerce-Signature', 'invalid');

    expect($validator->isValid($request, supportWebhookConfig('super-secret')))->toBeFalse()
        ->and($validator->isValid($request, supportWebhookConfig('')))->toBeFalse();
});

it('processes commerce webhooks and marks webhook calls as processed', function (): void {
    $webhookCall = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event' => 'payment.completed', 'id' => 'evt_123'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($webhookCall))->handle();

    expect(SupportWebhookProcessor::$processed)->toBe([
        ['payment.completed', ['event' => 'payment.completed', 'id' => 'evt_123']],
    ])->and($webhookCall->fresh()?->processed_at)->not->toBeNull();
});

it('processes duplicate webhook deliveries only once', function (): void {
    $webhookCall = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event' => 'payment.completed', 'id' => 'evt_dupe'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($webhookCall))->handle();
    (new SupportWebhookProcessor($webhookCall->fresh()))->handle();

    expect(SupportWebhookProcessor::$processed)->toBe([
        ['payment.completed', ['event' => 'payment.completed', 'id' => 'evt_dupe']],
    ])->and($webhookCall->fresh()?->processed_at)->not->toBeNull();
});

it('deduplicates processed provider events across separate webhook rows', function (): void {
    $first = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event' => 'payment.completed', 'id' => 'evt_provider_1'],
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event' => 'payment.completed', 'id' => 'evt_provider_1'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($first))->handle();
    (new SupportWebhookProcessor($second))->handle();

    expect(SupportWebhookProcessor::$processed)->toBe([
        ['payment.completed', ['event' => 'payment.completed', 'id' => 'evt_provider_1']],
    ])->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull();
});

it('does not deduplicate rows that share an event_id but have different event types', function (): void {
    $first = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event' => 'payment.completed', 'id' => 'evt_shared_id'],
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event' => 'payment.refunded', 'id' => 'evt_shared_id'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($first))->handle();
    (new SupportWebhookProcessor($second))->handle();

    // Both must be processed: same ID but different event types are distinct events.
    expect(SupportWebhookProcessor::$processed)->toBe([
        ['payment.completed', ['event' => 'payment.completed', 'id' => 'evt_shared_id']],
        ['payment.refunded', ['event' => 'payment.refunded', 'id' => 'evt_shared_id']],
    ])->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull();
});

it('deduplicates type-less rows with a shared event_id', function (): void {
    $first = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['id' => 'evt_typeless'],
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['id' => 'evt_typeless'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($first))->handle();
    (new SupportWebhookProcessor($second))->handle();

    // Both lack any event type: treated as the same typeless event, so only processed once.
    expect(SupportWebhookProcessor::$processed)->toHaveCount(1)
        ->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull();
});

it('does not cross-row deduplicate when no event_id is present', function (): void {
    $first = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event' => 'payment.completed'],
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event' => 'payment.completed'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($first))->handle();
    (new SupportWebhookProcessor($second))->handle();

    // Without an event_id, cross-row dedupe cannot run; both rows are processed independently.
    expect(SupportWebhookProcessor::$processed)->toHaveCount(2)
        ->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull();
});

it('processes all commerce webhooks by default', function (): void {
    expect((new CommerceWebhookProfile)->shouldProcess(Request::create('/webhooks/test', 'POST')))->toBeTrue();
});

function supportWebhookConfig(string $secret): WebhookConfig
{
    return new WebhookConfig([
        'name' => 'support-test',
        'signing_secret' => $secret,
        'signature_header_name' => 'X-Commerce-Signature',
        'signature_validator' => SupportWebhookSignatureValidator::class,
        'webhook_profile' => CommerceWebhookProfile::class,
        'webhook_model' => WebhookCall::class,
        'process_webhook_job' => SupportWebhookProcessor::class,
    ]);
}

final class SupportWebhookSignatureValidator extends CommerceSignatureValidator
{
    protected function getSignatureHeader(): string
    {
        return 'X-Commerce-Signature';
    }
}

final class SupportWebhookProcessor extends CommerceWebhookProcessor
{
    /**
     * @var array<int, array{0: string, 1: array<string, mixed>}>
     */
    public static array $processed = [];

    public static function reset(): void
    {
        self::$processed = [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        self::$processed[] = [$eventType, $payload];
    }
}
