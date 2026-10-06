<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsEngineTestCase;
use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Jobs\EvaluateSignalAlertsForEvent;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(SignalsEngineTestCase::class);

it('dispatches queued alert evaluation only after the outermost engine commit', function (string $engine): void {
    Queue::fake();
    config()->set('signals.features.alerts.evaluate_on_ingest.enabled', true);
    config()->set('signals.features.alerts.evaluate_on_ingest.queue', true);

    $this->useEngine($engine);

    $property = $this->engineProperty('Engine Alert Commit');

    DB::beginTransaction();

    try {
        app(IngestSignalEvent::class)->handle($property, [
            'event_name' => 'custom.engine-commit',
            'anonymous_id' => 'eng-commit-anon-' . Str::lower(Str::random(6)),
            'occurred_at' => '2026-10-04T10:00:00Z',
        ], true);

        Queue::assertNotPushed(EvaluateSignalAlertsForEvent::class);

        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();

        throw $e;
    }

    Queue::assertPushed(EvaluateSignalAlertsForEvent::class, 1);
    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(1);
})->with(['pgsql', 'mysql']);

it('discards queued alert evaluation when the outermost engine transaction rolls back', function (string $engine): void {
    Queue::fake();
    config()->set('signals.features.alerts.evaluate_on_ingest.enabled', true);
    config()->set('signals.features.alerts.evaluate_on_ingest.queue', true);

    $this->useEngine($engine);

    $property = $this->engineProperty('Engine Alert Rollback');

    DB::beginTransaction();

    app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'custom.engine-rollback',
        'anonymous_id' => 'eng-rollback-anon-' . Str::lower(Str::random(6)),
        'occurred_at' => '2026-10-04T10:00:00Z',
    ], true);

    Queue::assertNotPushed(EvaluateSignalAlertsForEvent::class);

    DB::rollBack();

    Queue::assertNotPushed(EvaluateSignalAlertsForEvent::class);
    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0);
})->with(['pgsql', 'mysql']);

it('runs inline alert effects only after the outermost engine commit', function (string $engine): void {
    Queue::fake();
    config()->set('signals.features.alerts.evaluate_on_ingest.enabled', true);
    config()->set('signals.features.alerts.evaluate_on_ingest.queue', false);

    $this->useEngine($engine);

    $property = $this->engineProperty('Engine Alert Inline');

    $rule = SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Engine Inline Rule',
        'slug' => 'engine-inline-rule-' . $engine . '-' . Str::lower(Str::random(6)),
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 0,
        'severity' => 'warning',
        'channels' => ['database'],
    ]);

    DB::beginTransaction();

    try {
        app(IngestSignalEvent::class)->handle($property, [
            'event_name' => 'custom.engine-inline',
            'anonymous_id' => 'eng-inline-anon-' . Str::lower(Str::random(6)),
            'occurred_at' => CarbonImmutable::now()->toAtomString(),
        ], true);

        expect(SignalAlertLog::query()->withoutOwnerScope()->where('signal_alert_rule_id', $rule->id)->count())->toBe(0);

        DB::commit();
    } catch (Throwable $e) {
        DB::rollBack();

        throw $e;
    }

    expect(SignalAlertLog::query()->withoutOwnerScope()->where('signal_alert_rule_id', $rule->id)->count())->toBe(1);

    DB::beginTransaction();

    app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'custom.engine-inline-rollback',
        'anonymous_id' => 'eng-inline-rb-anon-' . Str::lower(Str::random(6)),
        'occurred_at' => CarbonImmutable::now()->toAtomString(),
    ], true);

    expect(SignalAlertLog::query()->withoutOwnerScope()->where('signal_alert_rule_id', $rule->id)->count())->toBe(1);

    DB::rollBack();

    expect(SignalAlertLog::query()->withoutOwnerScope()->where('signal_alert_rule_id', $rule->id)->count())->toBe(1);
})->with(['pgsql', 'mysql']);
