<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Features\Alerts\Actions\ProcessAlertDelivery;
use AIArmada\Signals\Features\Alerts\Actions\SignalAlertDispatcher;
use AIArmada\Signals\Features\Alerts\EntryPoints\Jobs\DispatchSignalAlertDelivery;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertDelivery;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertLog;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

function deliveryFailureOwner(string $suffix): User
{
    return User::query()->create([
        'name' => 'Delivery Failure ' . $suffix,
        'email' => 'delivery-failure-' . $suffix . '@example.com',
        'password' => 'secret',
    ]);
}

function deliveryFailureRule(User $owner, string $suffix, ?TrackedProperty $property = null): SignalAlertRule
{
    config()->set('signals.features.alerts.allow_inline_destinations', true);

    return OwnerContext::withOwner($owner, function () use ($suffix, $property): SignalAlertRule {
        $property ??= TrackedProperty::query()->create([
            'name' => 'Delivery Failure ' . $suffix,
            'slug' => 'delivery-failure-' . $suffix . '-' . Str::lower(Str::random(6)),
            'type' => 'website',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        return SignalAlertRule::query()->create([
            'tracked_property_id' => $property->id,
            'name' => 'Delivery Failure ' . $suffix,
            'slug' => 'delivery-failure-rule-' . $suffix . '-' . Str::lower(Str::random(6)),
            'metric_key' => 'events',
            'operator' => '>=',
            'threshold' => 1,
            'timeframe_minutes' => 5,
            'cooldown_minutes' => 1,
            'severity' => 'warning',
            'channels' => ['email'],
            'destination_keys' => [],
            'inline_destinations' => [
                ['channel' => 'email', 'destination' => ['to' => 'ops@example.com']],
            ],
        ]);
    });
}

/**
 * @return array{SignalAlertRule, SignalAlertLog, SignalAlertDelivery}
 */
function deliveryFailureDispatch(User $owner, string $suffix): array
{
    Queue::fake();
    config()->set('signals.features.alerts.destinations.email.ops', ['to' => 'ops@example.com']);

    $rule = deliveryFailureRule($owner, $suffix);

    $log = OwnerContext::withOwner($owner, fn (): SignalAlertLog => app(SignalAlertDispatcher::class)->dispatch($rule, 2.0));
    $delivery = SignalAlertDelivery::query()->withoutOwnerScope()
        ->where('signal_alert_log_id', $log->id)
        ->firstOrFail();

    return [$rule, $log, $delivery];
}

it('records final failure from the job owner when ambient context is missing', function (): void {
    $owner = deliveryFailureOwner('missing');
    [, $log, $delivery] = deliveryFailureDispatch($owner, 'missing');

    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $job = new DispatchSignalAlertDelivery($delivery->id, $delivery->owner_type, $delivery->owner_id);
    $job->failed(new RuntimeException('transport failure'));

    expect($delivery->refresh()->status)->toBe('dead')
        ->and($delivery->dead_at)->not->toBeNull()
        ->and($log->refresh()->delivery_results['email']['status'] ?? null)->toBe('failed');
});

it('records final failure from the job owner under a foreign ambient owner', function (): void {
    $owner = deliveryFailureOwner('foreign');
    $foreign = deliveryFailureOwner('ambient');
    [, , $delivery] = deliveryFailureDispatch($owner, 'foreign');

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($foreign));

    $job = new DispatchSignalAlertDelivery($delivery->id, $delivery->owner_type, $delivery->owner_id);
    $job->failed(new RuntimeException('transport failure'));

    expect($delivery->refresh()->status)->toBe('dead')
        ->and($delivery->owner_id)->toBe($owner->getKey());
});

it('records final failure for explicit global deliveries', function (): void {
    Queue::fake();
    config()->set('signals.features.alerts.destinations.email.ops', ['to' => 'ops@example.com']);

    [$rule, $log, $delivery] = OwnerContext::withOwner(null, function (): array {
        $rule = SignalAlertRule::query()->create([
            'tracked_property_id' => null,
            'name' => 'Global Delivery Rule',
            'slug' => 'global-delivery-rule-' . Str::lower(Str::random(6)),
            'metric_key' => 'events',
            'operator' => '>=',
            'threshold' => 1,
            'timeframe_minutes' => 5,
            'cooldown_minutes' => 1,
            'severity' => 'warning',
            'channels' => ['email'],
            'destination_keys' => ['ops'],
        ]);
        $log = app(SignalAlertDispatcher::class)->dispatch($rule, 2.0);
        $delivery = SignalAlertDelivery::query()->withoutOwnerScope()
            ->where('signal_alert_log_id', $log->id)
            ->firstOrFail();

        return [$rule, $log, $delivery];
    });

    expect($delivery->owner_type)->toBeNull();

    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $job = new DispatchSignalAlertDelivery($delivery->id, null, null, true);
    $job->failed(new RuntimeException('transport failure'));

    expect($delivery->refresh()->status)->toBe('dead');
});

it('ignores final failure for a wrong job owner payload', function (): void {
    $owner = deliveryFailureOwner('victim');
    $intruder = deliveryFailureOwner('intruder');
    [, , $delivery] = deliveryFailureDispatch($owner, 'victim');

    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $job = new DispatchSignalAlertDelivery($delivery->id, $intruder->getMorphClass(), $intruder->getKey());
    $job->failed(new RuntimeException('transport failure'));

    expect($delivery->refresh()->status)->toBe('pending')
        ->and($delivery->dead_at)->toBeNull();
});

it('leaves sent deliveries terminal on final failure', function (): void {
    $owner = deliveryFailureOwner('sent');
    [, , $delivery] = deliveryFailureDispatch($owner, 'sent');

    OwnerContext::withOwner($owner, fn (): bool => $delivery->forceFill(['status' => 'sent'])->save());

    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $job = new DispatchSignalAlertDelivery($delivery->id, $delivery->owner_type, $delivery->owner_id);
    $job->failed(new RuntimeException('transport failure'));

    expect($delivery->refresh()->status)->toBe('sent')
        ->and($delivery->dead_at)->toBeNull();
});

it('leaves dead deliveries untouched on repeated final failure', function (): void {
    $owner = deliveryFailureOwner('dead');
    [, , $delivery] = deliveryFailureDispatch($owner, 'dead');

    $deadAt = now()->subHour();

    OwnerContext::withOwner($owner, fn (): bool => $delivery->forceFill([
        'status' => 'dead',
        'dead_at' => $deadAt,
        'last_error_code' => 'transport_error',
    ])->save());

    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $job = new DispatchSignalAlertDelivery($delivery->id, $delivery->owner_type, $delivery->owner_id);
    $job->failed(new RuntimeException('late transport failure'));

    $delivery->refresh();

    expect($delivery->status)->toBe('dead')
        ->and($delivery->dead_at?->toAtomString())->toBe($deadAt->toAtomString())
        ->and($delivery->last_error_code)->toBe('transport_error');
});

it('preserves the specific transport failure code when retries are exhausted', function (): void {
    $owner = deliveryFailureOwner('specific-code');
    [, , $delivery] = deliveryFailureDispatch($owner, 'specific-code');

    app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

    $failure = new RuntimeException('Signals alert delivery failed.', previous: new RuntimeException('http_502'));

    (new DispatchSignalAlertDelivery($delivery->id, $delivery->owner_type, $delivery->owner_id))->failed($failure);

    expect($delivery->refresh()->status)->toBe('dead')
        ->and($delivery->last_error_code)->toBe('http_502');
});

it('reports a retry delay while another worker holds an active lease', function (): void {
    $owner = deliveryFailureOwner('lease');
    [, , $delivery] = deliveryFailureDispatch($owner, 'lease');

    OwnerContext::withOwner($owner, fn (): bool => $delivery->forceFill([
        'status' => 'processing',
        'attempt_count' => 1,
        'leased_at' => CarbonImmutable::now(),
    ])->save());

    $retryAfter = OwnerContext::withOwner($owner, fn (): ?int => app(ProcessAlertDelivery::class)->run((string) $delivery->id));

    expect($retryAfter)->toBeInt()
        ->and($retryAfter)->toBeGreaterThan(0)
        ->and($delivery->refresh()->status)->toBe('processing')
        ->and($delivery->attempt_count)->toBe(1);
});

it('removes an orphaned delivery when its alert log was already deleted', function (): void {
    $owner = deliveryFailureOwner('orphan');
    [, $log, $delivery] = deliveryFailureDispatch($owner, 'orphan');

    SignalAlertLog::query()->withoutOwnerScope()->whereKey($log->id)->delete();

    $retryAfter = OwnerContext::withOwner($owner, fn (): ?int => app(ProcessAlertDelivery::class)->run((string) $delivery->id));

    expect($retryAfter)->toBeNull()
        ->and(SignalAlertDelivery::query()->withoutOwnerScope()->whereKey($delivery->id)->exists())->toBeFalse();
});
