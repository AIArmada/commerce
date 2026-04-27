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