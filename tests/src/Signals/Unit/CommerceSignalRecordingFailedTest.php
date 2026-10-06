<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Events\OfferCreated;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Exceptions\CommerceSignalRecordingFailed;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Contracts\Debug\ExceptionHandler;

uses(SignalsTestCase::class);

it('keeps previous exception text out of the wrapper message', function (): void {
    $previous = new RuntimeException("SQLSTATE[23000]: insert into signal_events (url) values ('https://secret.example/x?token=abc')");

    $failure = CommerceSignalRecordingFailed::forAutomaticRecording(
        AffiliateConversionRecorded::class,
        'recordAffiliateConversionRecorded',
        'recording',
        ['source_id' => 'source-1'],
        $previous,
    );

    expect($failure->getMessage())->not->toContain('secret.example')
        ->and($failure->getMessage())->toContain(RuntimeException::class)
        ->and($failure->getPrevious())->toBe($previous)
        ->and($failure->context())->toMatchArray([
            'source_event_class' => AffiliateConversionRecorded::class,
            'recorder_method' => 'recordAffiliateConversionRecorded',
            'phase' => 'recording',
            'integration' => 'affiliates',
            'source_id' => 'source-1',
        ]);
});

it('derives snake-case integration names from event classes', function (): void {
    $network = CommerceSignalRecordingFailed::forAutomaticRecording(
        OfferCreated::class,
        'recordOfferCreated',
        'recording',
        [],
        new RuntimeException('boom'),
    );

    $foreign = CommerceSignalRecordingFailed::forAutomaticRecording(
        'App\\Events\\SomethingHappened',
        'recordSomething',
        'mapping',
        [],
        new RuntimeException('boom'),
    );

    expect($network->context()['integration'])->toBe('affiliate_network')
        ->and($foreign->context())->not->toHaveKey('integration');
});

it('reports alert failures with persisted identifiers and no previous text', function (): void {
    $property = TrackedProperty::query()->create([
        'name' => 'Privacy Property',
        'slug' => 'privacy-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $event = SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => now(),
        'event_name' => 'custom.probe',
        'event_category' => 'custom',
        'ingestion_source' => SignalEvent::INGESTION_SOURCE_TRUSTED,
        'revenue_minor' => 0,
        'currency' => 'MYR',
    ]);

    $failure = CommerceSignalRecordingFailed::forAlertEvaluation(
        $event,
        $property,
        new RuntimeException('customer email customer@example.com leaked in evaluator'),
    );

    expect($failure->getMessage())->not->toContain('customer@example.com')
        ->and($failure->getPrevious())->toBeInstanceOf(RuntimeException::class)
        ->and($failure->context())->toMatchArray([
            'phase' => 'alert_evaluation',
            'signal_event_id' => $event->getKey(),
            'tracked_property_id' => $property->getKey(),
        ]);
});

it('swallows reporter failures when reporting safely', function (): void {
    app()->instance(ExceptionHandler::class, new class implements ExceptionHandler
    {
        public function report(Throwable $e)
        {
            throw new RuntimeException('reporter boom');
        }

        public function shouldReport(Throwable $e)
        {
            return true;
        }

        public function render($request, Throwable $e)
        {
            throw $e;
        }

        public function renderForConsole($output, Throwable $e)
        {
            throw $e;
        }
    });

    $failure = CommerceSignalRecordingFailed::forAutomaticRecording(
        AffiliateConversionRecorded::class,
        'recordAffiliateConversionRecorded',
        'recording',
        [],
        new RuntimeException('boom'),
    );

    CommerceSignalRecordingFailed::reportSafely($failure);

    expect(true)->toBeTrue();
});
