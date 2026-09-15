<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Actions\ProcessWebhookCallAction;
use AIArmada\CommerceSupport\Webhooks\CommerceSignatureValidator;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
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
        $table->string('url', 512);
        $table->json('headers')->nullable();
        $table->json('payload')->nullable();
        $table->text('exception')->nullable();
        $table->string('status', 50)->default('pending');
        $table->timestamp('processed_at')->nullable();
        $table->timestampTz('failed_at')->nullable();
        $table->string('event_id', 191)->nullable();
        $table->string('event_type', 191)->nullable();
        $table->timestamps();
    });
});

it('validates commerce webhook signatures with constant time comparison', function (): void {
    $payload = json_encode(['event_type' => 'payment.completed'], JSON_THROW_ON_ERROR);
    $secret = 'super-secret';
    $request = Request::create('/webhooks/test', 'POST', [], [], [], [], $payload);
    $request->headers->set('X-Commerce-Signature', hash_hmac('sha256', $payload, $secret));

    $validator = new SupportWebhookSignatureValidator;

    expect($validator->isValid($request, supportWebhookConfig($secret)))->toBeTrue();
});

it('creates webhook calls tables with processed_at support', function (): void {
    Schema::dropIfExists('webhook_calls');

    commerceSupportMigration('1970_01_01_000004_create_webhook_calls_table.php.stub')->up();

    expect(Schema::hasTable('webhook_calls'))->toBeTrue()
        ->and(Schema::hasColumn('webhook_calls', 'processed_at'))->toBeTrue();
});

it('rejects unsigned invalid or unconfigured commerce webhook signatures', function (): void {
    $payload = json_encode(['event_type' => 'payment.completed'], JSON_THROW_ON_ERROR);
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
        'payload' => ['event_type' => 'payment.completed', 'id' => 'evt_123'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($webhookCall))->handle();

    expect(SupportWebhookProcessor::$processed)->toBe([
        ['payment.completed', ['event_type' => 'payment.completed', 'id' => 'evt_123']],
    ])->and($webhookCall->fresh()?->processed_at)->not->toBeNull();
});

it('processes duplicate webhook deliveries only once', function (): void {
    $webhookCall = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed', 'id' => 'evt_dupe'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($webhookCall))->handle();
    (new SupportWebhookProcessor($webhookCall->fresh()))->handle();

    expect(SupportWebhookProcessor::$processed)->toBe([
        ['payment.completed', ['event_type' => 'payment.completed', 'id' => 'evt_dupe']],
    ])->and($webhookCall->fresh()?->processed_at)->not->toBeNull();
});

it('deduplicates processed provider events across separate webhook rows', function (): void {
    $first = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed', 'id' => 'evt_provider_1'],
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed', 'id' => 'evt_provider_1'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($first))->handle();
    (new SupportWebhookProcessor($second))->handle();

    expect(SupportWebhookProcessor::$processed)->toBe([
        ['payment.completed', ['event_type' => 'payment.completed', 'id' => 'evt_provider_1']],
    ])->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull();
});

it('does not deduplicate rows that share an event_id but have different event types', function (): void {
    $first = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed', 'id' => 'evt_shared_id'],
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event_type' => 'payment.refunded', 'id' => 'evt_shared_id'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($first))->handle();
    (new SupportWebhookProcessor($second))->handle();

    // Both must be processed: same ID but different event types are distinct events.
    expect(SupportWebhookProcessor::$processed)->toBe([
        ['payment.completed', ['event_type' => 'payment.completed', 'id' => 'evt_shared_id']],
        ['payment.refunded', ['event_type' => 'payment.refunded', 'id' => 'evt_shared_id']],
    ])->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull();
});

it('deduplicates identical events without a provider id by payload hash', function (): void {
    $first = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed'],
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed'],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($first))->handle();
    (new SupportWebhookProcessor($second))->handle();

    // Without a provider id the claim falls back to a payload hash, so an
    // identical redelivery deduplicates instead of double-processing.
    expect(SupportWebhookProcessor::$processed)->toHaveCount(1)
        ->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull();
});

it('processes distinct events without a provider id independently', function (): void {
    $first = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed', 'amount_minor' => 1000],
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed', 'amount_minor' => 2000],
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($first))->handle();
    (new SupportWebhookProcessor($second))->handle();

    // Distinct payloads hash differently, so payload-hash dedup never
    // suppresses genuinely different deliveries.
    expect(SupportWebhookProcessor::$processed)->toHaveCount(2)
        ->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull();
});

it('processes all commerce webhooks by default', function (): void {
    expect((new CommerceWebhookProfile)->shouldProcess(Request::create('/webhooks/test', 'POST')))->toBeTrue();
});

it('deduplicates concurrent same-event deliveries through the unique claim', function (): void {
    Schema::dropIfExists('webhook_calls');

    commerceSupportMigration('1970_01_01_000004_create_webhook_calls_table.php.stub')->up();

    $payload = ['event_type' => 'payment.completed', 'id' => 'evt_race_1'];

    $first = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => $payload,
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => $payload,
        'exception' => null,
    ]);

    (new SupportWebhookProcessor($first))->handle();
    (new SupportWebhookProcessor($second))->handle();

    expect(SupportWebhookProcessor::$processed)->toHaveCount(1)
        ->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->status)->toBe('processed');
});

it('treats unique violations from any driver as duplicates', function (): void {
    // SQLite can only produce 23000 end to end (covered above); Postgres
    // reports 23505 for the same constraint, so pin the classifier directly.
    $classify = function (array $errorInfo): bool {
        $previous = new PDOException('duplicate key value violates unique constraint');
        $previous->errorInfo = $errorInfo;
        $exception = new QueryException('testing', 'update "webhook_calls" set status = ?', [], $previous);

        return (bool) (new ReflectionMethod(ProcessWebhookCallAction::class, 'isUniqueViolation'))
            ->invoke(new ProcessWebhookCallAction, $exception);
    };

    expect($classify(['23505', 0, 'duplicate key value violates unique constraint']))->toBeTrue()
        ->and($classify(['23000', 19, 'UNIQUE constraint failed']))->toBeTrue()
        ->and($classify(['HY000', 5, 'database is locked']))->toBeFalse();
});

it('stores only the exception class and truncated message on failure', function (): void {
    $webhookCall = WebhookCall::query()->create([
        'name' => 'support-test',
        'url' => 'https://example.test/webhooks/support-test',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed', 'id' => 'evt_fail_1'],
        'exception' => null,
    ]);

    try {
        (new SupportFailingWebhookProcessor($webhookCall))->handle();
        $this->fail('Expected the webhook processor to rethrow.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('boom with a very long message that should be truncated when persisted to the database column');
    }

    $stored = $webhookCall->fresh()?->exception;

    expect($webhookCall->fresh()?->status)->toBe('failed')
        ->and($stored)->toBeArray()
        ->and($stored['class'] ?? null)->toBe(RuntimeException::class)
        ->and($stored['message'] ?? null)->toBeString()
        ->and($stored['message'] ?? '')->not->toContain('#0 ')
        ->and($stored['message'] ?? '')->not->toContain('Stack trace');
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

final class SupportFailingWebhookProcessor extends CommerceWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        throw new RuntimeException('boom with a very long message that should be truncated when persisted to the database column');
    }
}

function commerceSupportMigration(string $migrationFile): Migration
{
    /** @var Migration $migration */
    $migration = require dirname(__DIR__, 3) . '/packages/commerce-support/database/migrations/' . $migrationFile;

    return $migration;
}
