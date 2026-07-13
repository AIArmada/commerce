<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Jobs\DispatchSignalAlertDelivery;
use AIArmada\Signals\Models\SignalAlertDelivery;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\SignalAlertDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(SignalsTestCase::class);

function createAlertDeliveryRule(array $channels = ['database', 'webhook']): SignalAlertRule
{
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Alert Delivery Owner',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'secret',
    ]);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Alert Delivery Property',
        'slug' => 'alert-delivery-' . fake()->unique()->numerify('####'),
        'write_key' => fake()->unique()->uuid(),
    ]);

    return SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Revenue anomaly',
        'slug' => 'revenue-anomaly-' . fake()->unique()->numerify('####'),
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 5,
        'cooldown_minutes' => 1,
        'severity' => 'critical',
        'channels' => $channels,
        'destination_keys' => ['ops', 'backup'],
    ]);
}

it('persists per-destination deliveries and queues them without doing network work', function (): void {
    Queue::fake();
    config()->set('signals.features.alerts.destinations.webhook', [
        'ops' => ['url' => 'https://alerts.example.test/primary'],
        'backup' => ['url' => 'https://alerts.example.test/backup'],
    ]);

    $rule = createAlertDeliveryRule();
    $log = app(SignalAlertDispatcher::class)->dispatch($rule, 2.0);

    expect($log->deliveries()->count())->toBe(2)
        ->and($log->delivery_results['webhook']['status'] ?? null)->toBe('queued');

    Queue::assertPushed(DispatchSignalAlertDelivery::class, 2);
    Http::assertNothingSent();
});

it('records HTTP 500 as a failed attempt and duplicate successful jobs are idempotent', function (): void {
    config()->set('signals.features.alerts.destinations.webhook.ops', [
        'url' => 'https://alerts.example.test/primary',
    ]);
    app()->instance(PublicHttpUrlGuard::class, new PublicHttpUrlGuard(
        static fn (string $host): array => $host === 'alerts.example.test' ? ['93.184.216.34'] : [],
    ));
    app()->forgetInstance(SignalAlertDispatcher::class);
    Queue::fake();

    $rule = createAlertDeliveryRule(['database', 'webhook']);
    $log = app(SignalAlertDispatcher::class)->dispatch($rule, 2.0);
    $delivery = SignalAlertDelivery::query()->withoutOwnerScope()->where('signal_alert_log_id', $log->id)->firstOrFail();
    $job = new DispatchSignalAlertDelivery($delivery->id, $delivery->owner_type, $delivery->owner_id);

    Http::fake(['*' => Http::response([], 500)]);
    expect(fn () => $job->handle())->toThrow(RuntimeException::class);

    $delivery->refresh();
    expect($delivery->status)->toBe('failed')
        ->and($delivery->response_status)->toBeNull()
        ->and($delivery->last_error_code)->toBe('http_500');

    Http::fake(['*' => Http::response([], 204)]);
    $job->handle();
    $job->handle();

    $delivery->refresh();
    expect($delivery->status)->toBe('sent')
        ->and($delivery->attempt_count)->toBe(2);
    Http::assertSentCount(1);
});

it('represents partial destination success independently', function (): void {
    config()->set('signals.features.alerts.destinations.webhook', [
        'ops' => ['url' => 'https://alerts.example.test/primary'],
        'backup' => ['url' => 'https://alerts.example.test/backup'],
    ]);
    app()->instance(PublicHttpUrlGuard::class, new PublicHttpUrlGuard(
        static fn (string $host): array => ['93.184.216.34'],
    ));
    app()->forgetInstance(SignalAlertDispatcher::class);
    Queue::fake();

    $rule = createAlertDeliveryRule();
    $log = app(SignalAlertDispatcher::class)->dispatch($rule, 2.0);
    $deliveries = SignalAlertDelivery::query()->withoutOwnerScope()
        ->where('signal_alert_log_id', $log->id)
        ->orderBy('destination_key')
        ->get();

    Http::fakeSequence()->push([], 204)->push([], 500);
    $first = $deliveries->firstOrFail();
    $second = $deliveries->last();
    (new DispatchSignalAlertDelivery($first->id, $first->owner_type, $first->owner_id))->handle();
    expect(fn () => (new DispatchSignalAlertDelivery($second->id, $second->owner_type, $second->owner_id))->handle())
        ->toThrow(RuntimeException::class);

    $log->refresh();
    expect($log->delivery_results['webhook']['sent'] ?? null)->toBe(1)
        ->and($log->delivery_results['webhook']['failed'] ?? null)->toBe(1);
});
