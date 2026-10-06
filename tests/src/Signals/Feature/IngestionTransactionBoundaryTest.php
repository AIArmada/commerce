<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Jobs\EvaluateSignalAlertsForEvent;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

function transactionBoundaryProperty(string $suffix): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Transaction Boundary ' . $suffix,
        'slug' => 'transaction-boundary-' . $suffix . '-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);
}

it('leaves no resolution side effects when a duplicate retry returns the existing event', function (): void {
    $property = transactionBoundaryProperty('dedupe');

    $first = app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'custom.boundary',
        'anonymous_id' => 'boundary-anon-1',
        'session_identifier' => 'boundary-session-1',
        'session_started_at' => '2026-10-04T10:00:00Z',
        'occurred_at' => '2026-10-04T10:00:00Z',
        'path' => '/first',
        'idempotency_key' => 'boundary-dedupe-1',
    ], trusted: true);

    $sessionBefore = $first->signal_session_id !== null
        ? SignalSession::query()->withoutOwnerScope()->findOrFail($first->signal_session_id)->getAttributes()
        : null;
    $identityBefore = $first->signal_identity_id !== null
        ? SignalIdentity::query()->withoutOwnerScope()->findOrFail($first->signal_identity_id)->getAttributes()
        : null;

    $retry = app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'custom.boundary',
        'anonymous_id' => 'boundary-anon-new',
        'session_identifier' => 'boundary-session-new',
        'session_started_at' => '2026-10-04T11:00:00Z',
        'occurred_at' => '2026-10-04T11:00:00Z',
        'path' => '/retry-should-not-win',
        'idempotency_key' => 'boundary-dedupe-1',
    ], trusted: true);

    expect($retry->id)->toBe($first->id)
        ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(1)
        ->and(SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(1)
        ->and(SignalIdentity::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(1)
        ->and(SignalSession::query()->withoutOwnerScope()->where('session_identifier', 'boundary-session-new')->exists())->toBeFalse()
        ->and(SignalIdentity::query()->withoutOwnerScope()->where('anonymous_id', 'boundary-anon-new')->exists())->toBeFalse();

    $sessionAfter = SignalSession::query()->withoutOwnerScope()->findOrFail($first->signal_session_id)->getAttributes();
    $identityAfter = SignalIdentity::query()->withoutOwnerScope()->findOrFail($first->signal_identity_id)->getAttributes();

    expect($sessionAfter['exit_path'] ?? null)->toBe($sessionBefore['exit_path'] ?? null)
        ->and($sessionAfter['ended_at'] ?? null)->toBe($sessionBefore['ended_at'] ?? null)
        ->and($sessionAfter['duration_milliseconds'] ?? null)->toBe($sessionBefore['duration_milliseconds'] ?? null)
        ->and($sessionAfter['bounced_at'] ?? null)->toBe($sessionBefore['bounced_at'] ?? null)
        ->and($identityAfter['last_seen_at'] ?? null)->toBe($identityBefore['last_seen_at'] ?? null);
});

it('rolls back identity, session, and event together when ingestion fails after resolution', function (): void {
    Queue::fake();
    config()->set('signals.features.alerts.evaluate_on_ingest.enabled', true);
    config()->set('signals.features.alerts.evaluate_on_ingest.queue', true);

    $property = transactionBoundaryProperty('rollback');

    $failingName = new class
    {
        public function __toString(): string
        {
            throw new RuntimeException('injected ingestion failure');
        }
    };

    try {
        app(IngestSignalEvent::class)->handle($property, [
            'event_name' => $failingName,
            'anonymous_id' => 'rollback-anon-1',
            'session_identifier' => 'rollback-session-1',
            'occurred_at' => '2026-10-04T10:00:00Z',
        ], trusted: true);

        $this->fail('Expected ingestion to fail on the injected error.');
    } catch (Throwable $e) {
        expect($e->getMessage())->toContain('injected ingestion failure');
    }

    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0)
        ->and(SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0)
        ->and(SignalIdentity::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0);

    Queue::assertNotPushed(EvaluateSignalAlertsForEvent::class);
});

it('delays queued alert evaluation until the outermost transaction commits', function (): void {
    Queue::fake();
    config()->set('signals.features.alerts.evaluate_on_ingest.enabled', true);
    config()->set('signals.features.alerts.evaluate_on_ingest.queue', true);

    $property = transactionBoundaryProperty('outer-commit');

    DB::beginTransaction();

    try {
        app(IngestSignalEvent::class)->handle($property, [
            'event_name' => 'custom.outer-commit',
            'anonymous_id' => 'outer-commit-anon',
            'session_identifier' => 'outer-commit-session',
            'occurred_at' => '2026-10-04T10:00:00Z',
        ], trusted: true);

        Queue::assertNotPushed(EvaluateSignalAlertsForEvent::class);

        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();

        throw $e;
    }

    Queue::assertPushed(EvaluateSignalAlertsForEvent::class, 1);
    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(1);
});

it('discards queued and inline alert effects when the outermost transaction rolls back', function (): void {
    Queue::fake();
    config()->set('signals.features.alerts.evaluate_on_ingest.enabled', true);
    config()->set('signals.features.alerts.evaluate_on_ingest.queue', true);

    $property = transactionBoundaryProperty('outer-rollback');

    DB::beginTransaction();

    app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'custom.outer-rollback',
        'anonymous_id' => 'outer-rollback-anon',
        'session_identifier' => 'outer-rollback-session',
        'occurred_at' => '2026-10-04T10:00:00Z',
    ], trusted: true);

    Queue::assertNotPushed(EvaluateSignalAlertsForEvent::class);

    DB::rollBack();

    Queue::assertNotPushed(EvaluateSignalAlertsForEvent::class);
    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0);

    config()->set('signals.features.alerts.evaluate_on_ingest.queue', false);

    $rule = SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Outer Rollback Rule',
        'slug' => 'outer-rollback-rule-' . Str::lower(Str::random(6)),
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 0,
        'severity' => 'warning',
        'channels' => ['database'],
    ]);

    DB::beginTransaction();

    app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'custom.outer-rollback-inline',
        'anonymous_id' => 'outer-rollback-inline-anon',
        'occurred_at' => '2026-10-04T10:05:00Z',
    ], trusted: true);

    expect(SignalAlertLog::query()->withoutOwnerScope()->where('signal_alert_rule_id', $rule->id)->count())->toBe(0);

    DB::rollBack();

    expect(SignalAlertLog::query()->withoutOwnerScope()->where('signal_alert_rule_id', $rule->id)->count())->toBe(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0);
});
