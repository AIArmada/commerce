<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Communications\Actions\ApplyProviderEventAction;
use AIArmada\Communications\Contracts\CommunicationAuditRecorder;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Contracts\WebhookOwnerResolver;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\CommunicationDirection;
use AIArmada\Communications\Enums\CommunicationPriority;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Http\Controllers\WebhookController;
use AIArmada\Communications\Http\Middleware\VerifyWebhookSignature;
use AIArmada\Communications\Jobs\ProcessWebhookEventJob;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Services\NullCommunicationAuditRecorder;
use AIArmada\Communications\Webhooks\Normalizers\NullProviderEventNormalizer;
use AIArmada\Communications\Webhooks\Registrars\ProviderWebhookRegistrarService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class WebhookTestOwner extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'webhook_test_owners';
    }
}

beforeEach(function (): void {
    Schema::dropIfExists('webhook_test_owners');

    Schema::create('webhook_test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    Config::set('communications.http.route_prefix', 'communications');
    Config::set('services.webhooks.sendgrid.secret', 'test-secret');
});

test('route returns 202 for valid webhook request', function (): void {
    Queue::fake();

    $response = postSignedCommunicationsWebhook($this, [
        'event' => 'delivery.delivered',
        'delivery_id' => 'test-delivery-id',
    ]);

    $response->assertStatus(202);
    $response->assertJson(['status' => 'accepted']);
});

test('job is dispatched on valid webhook request', function (): void {
    Queue::fake();

    postSignedCommunicationsWebhook($this, [
        'event' => 'delivery.delivered',
        'delivery_id' => 'test-delivery-id',
    ]);

    Queue::assertPushed(ProcessWebhookEventJob::class, function (ProcessWebhookEventJob $job): bool {
        return $job->provider === 'sendgrid'
            && $job->payload['event'] === 'delivery.delivered';
    });
});

test('route uses configurable middleware', function (): void {
    $middleware = config('communications.webhooks.middleware');
    expect($middleware)->toContain('api', VerifyWebhookSignature::class);
});

test('provider webhook registrar falls back to services secrets', function (): void {
    Config::set('services.webhooks.sendgrid.secret', 'test-secret');

    $registrar = new ProviderWebhookRegistrarService;

    expect($registrar->getSecret('sendgrid'))->toBe('test-secret');
});

test('signature middleware aborts on missing signature when secret is set', function (): void {
    Config::set('services.webhooks.sendgrid.secret', 'test-secret');

    $request = Request::create(
        'communications/webhooks/sendgrid',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['event' => 'delivery.delivered'])
    );

    $request->setRouteResolver(function () use ($request) {
        $route = Route::post('communications/webhooks/{provider}', [WebhookController::class, 'handle']);
        $route->bind($request);
        $route->setParameter('provider', 'sendgrid');

        return $route;
    });

    $middleware = new VerifyWebhookSignature(new ProviderWebhookRegistrarService);

    try {
        $middleware->handle($request, fn ($req) => response()->json(['status' => 'ok']));
        $this->fail('Expected HttpException was not thrown.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(401);
    }
});

test('recordWebhookReplay does not throw on NullCommunicationAuditRecorder', function (): void {
    $recorder = new NullCommunicationAuditRecorder;

    $recorder->recordWebhookReplay(
        communicationId: 'test-comm-id',
        actorType: null,
        actorId: null,
        reason: 'test replay',
        metadata: ['test' => true],
    );

    expect(true)->toBeTrue();
});

test('NullCommunicationAuditRecorder implements CommunicationAuditRecorder', function (): void {
    $recorder = new NullCommunicationAuditRecorder;

    expect($recorder)->toBeInstanceOf(
        CommunicationAuditRecorder::class,
    );
});

test('signature middleware accepts valid signature', function (): void {
    $secret = 'test-secret';
    Config::set('services.webhooks.sendgrid.secret', $secret);

    $payload = ['event' => 'delivery.delivered', 'delivery_id' => 'test-id'];
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret);

    $request = Request::create(
        'communications/webhooks/sendgrid',
        'POST',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_Webhook_Signature' => $signature,
        ],
        $body
    );

    $request->setRouteResolver(function () use ($request) {
        $route = Route::post('communications/webhooks/{provider}', [WebhookController::class, 'handle']);
        $route->bind($request);
        $route->setParameter('provider', 'sendgrid');

        return $route;
    });

    $middleware = new VerifyWebhookSignature(new ProviderWebhookRegistrarService);
    $response = $middleware->handle($request, fn ($req) => response()->json(['status' => 'ok']));

    expect($response->getStatusCode())->toBe(200);
});

test('signature middleware rejects providers without a configured secret', function (): void {
    Config::set('services.webhooks.sendgrid.secret');
    Config::set('services.webhooks.secret');

    $request = Request::create(
        'communications/webhooks/sendgrid',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['event' => 'delivery.delivered'])
    );

    $request->setRouteResolver(function () use ($request) {
        $route = Route::post('communications/webhooks/{provider}', [WebhookController::class, 'handle']);
        $route->bind($request);
        $route->setParameter('provider', 'sendgrid');

        return $route;
    });

    expect(fn () => (new VerifyWebhookSignature(new ProviderWebhookRegistrarService))
        ->handle($request, fn ($req) => response()->json(['status' => 'ok'])))
        ->toThrow(HttpException::class);
});

test('webhook payload cannot choose its owner context', function (): void {
    Queue::fake();

    postSignedCommunicationsWebhook($this, [
        'event' => 'delivery.delivered',
        '__owner_type' => WebhookTestOwner::class,
        '__owner_id' => 'attacker-owner',
    ]);

    Queue::assertPushed(ProcessWebhookEventJob::class, function (ProcessWebhookEventJob $job): bool {
        return $job->ownerType === null
            && $job->ownerId === null
            && ! array_key_exists('__owner_type', $job->payload)
            && ! array_key_exists('__owner_id', $job->payload);
    });
});

test('webhook owner context comes from the configured resolver', function (): void {
    Queue::fake();

    $owner = WebhookTestOwner::query()->create(['name' => 'Resolved Owner']);

    app()->instance(WebhookOwnerResolver::class, new class($owner) implements WebhookOwnerResolver
    {
        public function __construct(
            private readonly Model $owner,
        ) {}

        public function resolve(string $provider, array $payload): ?Model
        {
            return $this->owner;
        }
    });

    postSignedCommunicationsWebhook($this, [
        'event' => 'delivery.delivered',
    ]);

    Queue::assertPushed(ProcessWebhookEventJob::class, function (ProcessWebhookEventJob $job) use ($owner): bool {
        return $job->ownerType === $owner->getMorphClass()
            && $job->ownerId === (string) $owner->getKey();
    });
});

test('process webhook job restores owner context before applying the event', function (): void {
    $owner = WebhookTestOwner::query()->create([
        'name' => 'Webhook Owner',
    ]);

    $communication = OwnerContext::withOwner($owner, function (): Communication {
        return Communication::create([
            'direction' => CommunicationDirection::Outbound,
            'category' => CommunicationCategory::Transactional,
            'priority' => CommunicationPriority::Normal,
            'purpose' => 'webhook-owner-test',
            'status' => CommunicationStatus::Draft,
        ]);
    });

    $recipient = OwnerContext::withOwner($owner, function () use ($communication): CommunicationRecipient {
        return CommunicationRecipient::create([
            'communication_id' => $communication->id,
            'role' => 'to',
        ]);
    });

    $delivery = OwnerContext::withOwner($owner, function () use ($communication, $recipient): CommunicationDelivery {
        return CommunicationDelivery::create([
            'communication_id' => $communication->id,
            'recipient_id' => $recipient->id,
            'channel' => 'mail',
            'provider' => 'array',
            'status' => DeliveryStatus::Pending,
            'attempt_count' => 0,
            'max_attempts' => 3,
        ]);
    });

    $job = new ProcessWebhookEventJob(
        provider: 'sendgrid',
        payload: [
            'id' => 'evt-owner-1',
            'event' => 'delivery',
            'communication_id' => $communication->id,
            'delivery_id' => $delivery->id,
        ],
        ownerId: (string) $owner->getKey(),
        ownerType: WebhookTestOwner::class,
    );
    $lock = new TrackingWebhookLock;

    $job->handle(
        new NullProviderEventNormalizer,
        app(ApplyProviderEventAction::class),
        $lock,
    );

    expect($delivery->fresh()->status->value)->toBe('delivered')
        ->and($lock->acquireCalls)->toBe(1)
        ->and($lock->lastTtlSeconds)->toBe(3600)
        ->and($lock->releaseCalls)->toBe(0);
});

test('process webhook job stops when it cannot acquire the idempotency lock', function (): void {
    $lock = new RejectingWebhookLock;
    $job = new ProcessWebhookEventJob(
        provider: 'sendgrid',
        payload: ['event' => 'unknown'],
    );

    $job->handle(
        new NullProviderEventNormalizer,
        app(ApplyProviderEventAction::class),
        $lock,
    );

    expect($lock->acquireCalls)->toBe(1)
        ->and($lock->releaseCalls)->toBe(0);
});

test('process webhook job releases its idempotency lock after failure', function (): void {
    $lock = new TrackingWebhookLock;
    $job = new ProcessWebhookEventJob(
        provider: 'sendgrid',
        payload: ['event' => 'unknown'],
    );

    expect(fn () => $job->handle(
        new NullProviderEventNormalizer,
        app(ApplyProviderEventAction::class),
        $lock,
    ))->toThrow(RuntimeException::class);

    expect($lock->acquireCalls)->toBe(1)
        ->and($lock->releaseCalls)->toBe(1);
});

/**
 * @param  array<string, mixed>  $payload
 */
function postSignedCommunicationsWebhook(object $testCase, array $payload)
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $body, 'test-secret');

    return $testCase->call(
        'POST',
        'communications/webhooks/sendgrid',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        ],
        $body,
    );
}

final class RejectingWebhookLock implements IdempotencyLock
{
    public int $acquireCalls = 0;

    public int $releaseCalls = 0;

    public function acquire(string $key, int $ttlSeconds): bool
    {
        $this->acquireCalls++;

        return false;
    }

    public function release(string $key): void
    {
        $this->releaseCalls++;
    }

    public function exists(string $key): bool
    {
        return false;
    }
}

final class TrackingWebhookLock implements IdempotencyLock
{
    public int $acquireCalls = 0;

    public int $releaseCalls = 0;

    public ?int $lastTtlSeconds = null;

    public function acquire(string $key, int $ttlSeconds): bool
    {
        $this->acquireCalls++;
        $this->lastTtlSeconds = $ttlSeconds;

        return true;
    }

    public function release(string $key): void
    {
        $this->releaseCalls++;
    }

    public function exists(string $key): bool
    {
        return $this->acquireCalls > $this->releaseCalls;
    }
}
