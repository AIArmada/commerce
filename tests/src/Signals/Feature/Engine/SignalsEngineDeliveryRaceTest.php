<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsEngineTestCase;
use AIArmada\Commerce\Tests\Signals\Support\EngineRace;
use AIArmada\CommerceSupport\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\CommerceSupport\Support\ValidatedHttpTarget;
use AIArmada\Signals\Jobs\DispatchSignalAlertDelivery;
use AIArmada\Signals\Models\SignalAlertDelivery;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Services\SignalAlertDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(SignalsEngineTestCase::class);

function engineDeliveryRepeats(): int
{
    return 5;
}

/**
 * Fake outbound URL guard: accepts the configured test URL without DNS.
 * Never touches the network.
 */
final class SigengFakeUrlGuard
{
    public function validate(string $url): ValidatedHttpTarget
    {
        return new ValidatedHttpTarget(
            url: $url,
            scheme: 'https',
            host: 'alerts.example.test',
            port: 443,
            addresses: ['93.184.216.34'],
            selectedIp: '93.184.216.34',
            isIpLiteral: false,
        );
    }
}

/**
 * Fake pinned HTTP client: blocks inside the transport until the
 * coordinating worker releases it, then succeeds or throws per mode.
 * Never touches the network.
 */
final class SigengBlockingHttpClient
{
    public function __construct(
        private readonly string $workdir,
        private readonly string $mode,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $headers
     */
    public function send(
        string $method,
        ValidatedHttpTarget $target,
        array $options = [],
        array $headers = [],
        int $connectTimeout = 3,
        int $timeout = 10,
        int $attempts = 1,
        int $retrySleepMilliseconds = 0,
    ): Response {
        file_put_contents($this->workdir . '/transport-entered', (string) getmypid());

        $deadline = microtime(true) + 60;

        while (! is_file($this->workdir . '/transport-release')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('sigeng transport release timed out.');
            }

            usleep(1000);
        }

        if ($this->mode === 'error') {
            throw new RuntimeException('sigeng invalid_destination probe');
        }

        return new Response(new GuzzleHttp\Psr7\Response(200, [], '{}'));
    }
}

/**
 * @return array{SignalAlertRule, SignalAlertDelivery}
 */
function engineDeliveryDispatch(SignalsEngineTestCase $test, User $owner, string $suffix): array
{
    Queue::fake();
    config()->set('signals.features.alerts.destinations.email.ops', ['to' => 'ops@example.com']);

    return OwnerContext::withOwner($owner, function () use ($test, $suffix): array {
        $property = $test->engineProperty('Engine Delivery ' . $suffix);

        $rule = SignalAlertRule::query()->create([
            'tracked_property_id' => $property->id,
            'name' => 'Engine Delivery ' . $suffix,
            'slug' => 'engine-delivery-rule-' . $suffix . '-' . Str::lower(Str::random(6)),
            'metric_key' => 'events',
            'operator' => '>=',
            'threshold' => 1,
            'timeframe_minutes' => 5,
            'cooldown_minutes' => 0,
            'severity' => 'warning',
            'channels' => ['email'],
            'destination_keys' => ['ops'],
        ]);

        $log = app(SignalAlertDispatcher::class)->dispatch($rule, 2.0);
        $delivery = SignalAlertDelivery::query()->withoutOwnerScope()
            ->where('signal_alert_log_id', $log->id)
            ->firstOrFail();

        return [$rule, $delivery];
    });
}

/**
 * @return array{SignalAlertRule, SignalAlertDelivery}
 */
function engineWebhookDeliveryDispatch(SignalsEngineTestCase $test, User $owner, string $suffix): array
{
    Queue::fake();
    config()->set('signals.features.alerts.destinations.webhook.ops', ['url' => 'https://alerts.example.test/hook']);

    return OwnerContext::withOwner($owner, function () use ($test, $suffix): array {
        $property = $test->engineProperty('Engine Webhook ' . $suffix);

        $rule = SignalAlertRule::query()->create([
            'tracked_property_id' => $property->id,
            'name' => 'Engine Webhook ' . $suffix,
            'slug' => 'engine-webhook-rule-' . $suffix . '-' . Str::lower(Str::random(6)),
            'metric_key' => 'events',
            'operator' => '>=',
            'threshold' => 1,
            'timeframe_minutes' => 5,
            'cooldown_minutes' => 0,
            'severity' => 'warning',
            'channels' => ['webhook'],
            'destination_keys' => ['ops'],
        ]);

        $log = app(SignalAlertDispatcher::class)->dispatch($rule, 2.0);
        $delivery = SignalAlertDelivery::query()->withoutOwnerScope()
            ->where('signal_alert_log_id', $log->id)
            ->firstOrFail();

        return [$rule, $delivery];
    });
}

it('leaves sent deliveries untouched under genuine concurrent final failure', function (string $engine): void {
    $this->useEngine($engine);

    for ($round = 0; $round < engineDeliveryRepeats(); $round++) {
        $owner = $this->engineOwner;
        [, $delivery] = engineDeliveryDispatch($this, $owner, 'sent-' . $engine . '-' . $round);

        $sentAt = CarbonImmutable::parse('2026-10-04T09:00:00Z');

        OwnerContext::withOwner($owner, static fn (): bool => $delivery->forceFill([
            'status' => 'sent',
            'sent_at' => $sentAt,
            'leased_at' => null,
            'last_error_code' => null,
        ])->save());

        $deliveryId = (string) $delivery->id;
        $ownerType = $delivery->owner_type;
        $ownerId = $delivery->owner_id;

        EngineRace::run([
            'a' => static function () use ($deliveryId, $ownerType, $ownerId): array {
                (new DispatchSignalAlertDelivery($deliveryId, $ownerType, $ownerId))->failed(new RuntimeException('late failure a'));

                return ['done' => true];
            },
            'b' => static function () use ($deliveryId, $ownerType, $ownerId): array {
                (new DispatchSignalAlertDelivery($deliveryId, $ownerType, $ownerId))->failed(new RuntimeException('late failure b'));

                return ['done' => true];
            },
        ]);

        $delivery->refresh();

        expect($delivery->status)->toBe('sent')
            ->and($delivery->sent_at?->toAtomString())->toBe($sentAt->toAtomString())
            ->and($delivery->dead_at)->toBeNull()
            ->and($delivery->last_error_code)->toBeNull();
    }
})->with(['pgsql', 'mysql']);

it('leaves dead deliveries untouched under genuine concurrent final failure', function (string $engine): void {
    $this->useEngine($engine);

    for ($round = 0; $round < engineDeliveryRepeats(); $round++) {
        $owner = $this->engineOwner;
        [, $delivery] = engineDeliveryDispatch($this, $owner, 'dead-' . $engine . '-' . $round);

        $deadAt = CarbonImmutable::parse('2026-10-04T09:00:00Z');

        OwnerContext::withOwner($owner, static fn (): bool => $delivery->forceFill([
            'status' => 'dead',
            'dead_at' => $deadAt,
            'leased_at' => null,
            'last_error_code' => 'transport_error',
        ])->save());

        $deliveryId = (string) $delivery->id;
        $ownerType = $delivery->owner_type;
        $ownerId = $delivery->owner_id;

        EngineRace::run([
            'a' => static function () use ($deliveryId, $ownerType, $ownerId): array {
                (new DispatchSignalAlertDelivery($deliveryId, $ownerType, $ownerId))->failed(new RuntimeException('late failure a'));

                return ['done' => true];
            },
            'b' => static function () use ($deliveryId, $ownerType, $ownerId): array {
                (new DispatchSignalAlertDelivery($deliveryId, $ownerType, $ownerId))->failed(new RuntimeException('late failure b'));

                return ['done' => true];
            },
        ]);

        $delivery->refresh();

        expect($delivery->status)->toBe('dead')
            ->and($delivery->dead_at?->toAtomString())->toBe($deadAt->toAtomString())
            ->and($delivery->last_error_code)->toBe('transport_error');
    }
})->with(['pgsql', 'mysql']);

it('keeps terminal delivery integrity when final failure races a live send transition', function (string $engine): void {
    Mail::fake();

    $this->useEngine($engine);

    $outcomes = [];

    for ($round = 0; $round < 10; $round++) {
        $owner = $this->engineOwner;
        [, $delivery] = engineDeliveryDispatch($this, $owner, 'mixed-' . $engine . '-' . $round);

        expect($delivery->status)->toBe('pending');

        $deliveryId = (string) $delivery->id;
        $ownerType = $delivery->owner_type;
        $ownerId = $delivery->owner_id;

        EngineRace::run([
            'fail' => static function () use ($deliveryId, $ownerType, $ownerId): array {
                (new DispatchSignalAlertDelivery($deliveryId, $ownerType, $ownerId))->failed(new RuntimeException('racing failure'));

                return ['done' => true];
            },
            'send' => static function () use ($deliveryId, $ownerType, $ownerId): array {
                (new DispatchSignalAlertDelivery($deliveryId, $ownerType, $ownerId))->handle();

                return ['done' => true];
            },
        ]);

        $delivery->refresh();

        $outcomes[] = $delivery->status . '|sent_at:' . ($delivery->sent_at === null ? 'null' : 'set')
            . '|dead_at:' . ($delivery->dead_at === null ? 'null' : 'set');

        expect($delivery->status)->toBeIn(['sent', 'dead']);

        if ($delivery->status === 'sent') {
            expect($delivery->sent_at)->not->toBeNull()
                ->and($delivery->dead_at)->toBeNull('mixed terminal timestamps: ' . implode(' ; ', $outcomes));
        } else {
            expect($delivery->dead_at)->not->toBeNull()
                ->and($delivery->sent_at)->toBeNull('mixed terminal timestamps: ' . implode(' ; ', $outcomes));
        }
    }
})->with(['pgsql', 'mysql']);

it('preserves a final dead row when a paused transport send resumes with success', function (string $engine): void {
    $this->useEngine($engine);

    for ($round = 0; $round < engineDeliveryRepeats(); $round++) {
        $owner = $this->engineOwner;
        [, $delivery] = engineWebhookDeliveryDispatch($this, $owner, 'paused-success-' . $engine . '-' . $round);

        expect($delivery->status)->toBe('pending');

        $deliveryId = (string) $delivery->id;
        $ownerType = $delivery->owner_type;
        $ownerId = $delivery->owner_id;

        // Deterministic order on separate connections: the sender claims and
        // blocks inside the fake transport; the failer finalizes dead only
        // after the sender provably entered the transport, then releases it.
        $results = EngineRace::run([
            'sender' => static function (string $workdir) use ($deliveryId, $ownerType, $ownerId): array {
                app()->instance(PublicHttpUrlGuard::class, new SigengFakeUrlGuard);
                app()->instance(PinnedHttpClient::class, new SigengBlockingHttpClient($workdir, 'success'));

                (new DispatchSignalAlertDelivery($deliveryId, $ownerType, $ownerId))->handle();

                return ['entered_transport' => is_file($workdir . '/transport-entered')];
            },
            'failer' => static function (string $workdir) use ($deliveryId, $ownerType, $ownerId): array {
                $deadline = microtime(true) + 60;

                while (! is_file($workdir . '/transport-entered')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('failer timed out waiting for the paused transport.');
                    }

                    usleep(1000);
                }

                (new DispatchSignalAlertDelivery($deliveryId, $ownerType, $ownerId))->failed(new RuntimeException('sigeng deterministic failure'));

                $observed = SignalAlertDelivery::query()->withoutOwnerScope()->findOrFail($deliveryId);

                file_put_contents($workdir . '/transport-release', (string) getmypid());

                return [
                    'observed_status' => $observed->status,
                    'observed_dead_at' => $observed->dead_at?->toAtomString(),
                    'observed_error' => $observed->last_error_code,
                ];
            },
        ]);

        expect($results['sender']['entered_transport'])->toBeTrue()
            ->and($results['failer']['observed_status'])->toBe('dead')
            ->and($results['failer']['observed_dead_at'])->not->toBeNull();

        $delivery->refresh();

        expect($delivery->status)->toBe('dead')
            ->and($delivery->dead_at?->toAtomString())->toBe($results['failer']['observed_dead_at'])
            ->and($delivery->sent_at)->toBeNull()
            ->and($delivery->response_status)->toBeNull()
            ->and($delivery->last_error_code)->toBe($results['failer']['observed_error']);
    }
})->with(['pgsql', 'mysql']);

it('preserves a final dead row when a paused transport error resumes after it', function (string $engine): void {
    $this->useEngine($engine);

    for ($round = 0; $round < engineDeliveryRepeats(); $round++) {
        $owner = $this->engineOwner;
        [, $delivery] = engineWebhookDeliveryDispatch($this, $owner, 'paused-error-' . $engine . '-' . $round);

        expect($delivery->status)->toBe('pending');

        $deliveryId = (string) $delivery->id;
        $ownerType = $delivery->owner_type;
        $ownerId = $delivery->owner_id;

        $results = EngineRace::run([
            'sender' => static function (string $workdir) use ($deliveryId, $ownerType, $ownerId): array {
                app()->instance(PublicHttpUrlGuard::class, new SigengFakeUrlGuard);
                app()->instance(PinnedHttpClient::class, new SigengBlockingHttpClient($workdir, 'error'));

                try {
                    (new DispatchSignalAlertDelivery($deliveryId, $ownerType, $ownerId))->handle();
                } catch (RuntimeException $e) {
                    if ($e->getMessage() !== 'Signals alert delivery failed.') {
                        throw $e;
                    }

                    return [
                        'entered_transport' => is_file($workdir . '/transport-entered'),
                        'thrown' => $e->getMessage(),
                    ];
                }

                throw new RuntimeException('Expected the fake transport error to fail the send.');
            },
            'failer' => static function (string $workdir) use ($deliveryId, $ownerType, $ownerId): array {
                $deadline = microtime(true) + 60;

                while (! is_file($workdir . '/transport-entered')) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('failer timed out waiting for the paused transport.');
                    }

                    usleep(1000);
                }

                (new DispatchSignalAlertDelivery($deliveryId, $ownerType, $ownerId))->failed(new RuntimeException('sigeng deterministic failure'));

                $observed = SignalAlertDelivery::query()->withoutOwnerScope()->findOrFail($deliveryId);

                file_put_contents($workdir . '/transport-release', (string) getmypid());

                return [
                    'observed_status' => $observed->status,
                    'observed_dead_at' => $observed->dead_at?->toAtomString(),
                    'observed_error' => $observed->last_error_code,
                ];
            },
        ]);

        expect($results['sender']['entered_transport'])->toBeTrue()
            ->and($results['sender']['thrown'])->toBe('Signals alert delivery failed.')
            ->and($results['failer']['observed_status'])->toBe('dead')
            ->and($results['failer']['observed_error'])->toBe('transport_error');

        $delivery->refresh();

        // The late transport error (which would map to invalid_destination)
        // must not regress the final dead row back to failed.
        expect($delivery->status)->toBe('dead')
            ->and($delivery->dead_at?->toAtomString())->toBe($results['failer']['observed_dead_at'])
            ->and($delivery->sent_at)->toBeNull()
            ->and($delivery->last_error_code)->toBe('transport_error');
    }
})->with(['pgsql', 'mysql']);
