<?php

declare(strict_types=1);

use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Webhooks\WebhookMonitor;
use Carbon\CarbonImmutable;

describe('WebhookMonitor without database', function (): void {
    it('can be instantiated', function (): void {
        $monitor = new WebhookMonitor;
        expect($monitor)->toBeInstanceOf(WebhookMonitor::class);
    });

    it('calculates health metrics from stored webhooks', function (): void {
        $since = CarbonImmutable::parse('2026-04-22 08:00:00');

        Webhook::create([
            'title' => 'Processed webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['id' => 'purchase-processed'],
            'status' => 'processed',
            'processed' => true,
            'processing_time_ms' => 100.0,
            'created_at' => $since->addHour(),
            'updated_at' => $since->addHour(),
            'created_on' => $since->addHour()->timestamp,
            'updated_on' => $since->addHour()->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        Webhook::create([
            'title' => 'Failed webhook',
            'event' => 'purchase.failed',
            'events' => ['purchase.failed'],
            'payload' => ['id' => 'purchase-failed'],
            'status' => 'failed',
            'processed' => false,
            'processing_time_ms' => 200.0,
            'created_at' => $since->addHours(2),
            'updated_at' => $since->addHours(2),
            'created_on' => $since->addHours(2)->timestamp,
            'updated_on' => $since->addHours(2)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        Webhook::create([
            'title' => 'Pending webhook',
            'event' => 'purchase.created',
            'events' => ['purchase.created'],
            'payload' => ['id' => 'purchase-pending'],
            'status' => 'pending',
            'processed' => false,
            'processing_time_ms' => null,
            'created_at' => $since->addHours(3),
            'updated_at' => $since->addHours(3),
            'created_on' => $since->addHours(3)->timestamp,
            'updated_on' => $since->addHours(3)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        Webhook::create([
            'title' => 'Old webhook',
            'event' => 'purchase.old',
            'events' => ['purchase.old'],
            'payload' => ['id' => 'purchase-old'],
            'status' => 'processed',
            'processed' => true,
            'processing_time_ms' => 999.0,
            'created_at' => $since->subHour(),
            'updated_at' => $since->subHour(),
            'created_on' => $since->subHour()->timestamp,
            'updated_on' => $since->subHour()->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        $health = (new WebhookMonitor)->getHealth($since);

        expect($health->total)->toBe(3)
            ->and($health->processed)->toBe(1)
            ->and($health->failed)->toBe(1)
            ->and($health->pending)->toBe(1)
            ->and($health->successRate)->toBe(33.33)
            ->and($health->avgProcessingTimeMs)->toBe(150.0);
    });

    it('groups hourly webhook volume using stored timestamps', function (): void {
        $since = CarbonImmutable::parse('2026-04-22 10:00:00');

        Webhook::create([
            'title' => 'Hour one processed',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['id' => 'purchase-hour-1-a'],
            'status' => 'processed',
            'processed' => true,
            'created_at' => $since->addMinutes(5),
            'updated_at' => $since->addMinutes(5),
            'created_on' => $since->addMinutes(5)->timestamp,
            'updated_on' => $since->addMinutes(5)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        Webhook::create([
            'title' => 'Hour one failed',
            'event' => 'purchase.failed',
            'events' => ['purchase.failed'],
            'payload' => ['id' => 'purchase-hour-1-b'],
            'status' => 'failed',
            'processed' => false,
            'created_at' => $since->addMinutes(25),
            'updated_at' => $since->addMinutes(25),
            'created_on' => $since->addMinutes(25)->timestamp,
            'updated_on' => $since->addMinutes(25)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        Webhook::create([
            'title' => 'Hour two processed',
            'event' => 'purchase.created',
            'events' => ['purchase.created'],
            'payload' => ['id' => 'purchase-hour-2-a'],
            'status' => 'processed',
            'processed' => true,
            'created_at' => $since->addHour()->addMinutes(15),
            'updated_at' => $since->addHour()->addMinutes(15),
            'created_on' => $since->addHour()->addMinutes(15)->timestamp,
            'updated_on' => $since->addHour()->addMinutes(15)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        $volume = (new WebhookMonitor)->getHourlyVolume($since);

        expect($volume)->toBe([
            '2026-04-22 10:00:00' => [
                'total' => 2,
                'processed' => 1,
                'failed' => 1,
            ],
            '2026-04-22 11:00:00' => [
                'total' => 1,
                'processed' => 1,
                'failed' => 0,
            ],
        ]);
    });
});
