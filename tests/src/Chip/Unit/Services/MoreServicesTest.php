<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\RecurringInterval;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\LocalAnalyticsService;
use AIArmada\Chip\Services\MetricsAggregator;
use AIArmada\Chip\Services\RecurringService;
use Illuminate\Support\Carbon;

describe('LocalAnalyticsService', function (): void {
    it('can be instantiated', function (): void {
        $service = new LocalAnalyticsService;
        expect($service)->toBeInstanceOf(LocalAnalyticsService::class);
    });
});

describe('MetricsAggregator', function (): void {
    it('can be instantiated', function (): void {
        $aggregator = new MetricsAggregator;
        expect($aggregator)->toBeInstanceOf(MetricsAggregator::class);
    });

    it('can calculate backfill days', function (): void {
        $aggregator = Mockery::mock(MetricsAggregator::class)->makePartial();
        $aggregator->shouldReceive('aggregateForDate')
            ->times(7);

        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-01-07');

        $days = $aggregator->backfill($startDate, $endDate);

        expect($days)->toBe(7);
    });
});

describe('RecurringService', function (): void {
    it('can be instantiated', function (): void {
        $chipService = Mockery::mock(ChipCollectService::class);
        $service = new RecurringService($chipService);

        expect($service)->toBeInstanceOf(RecurringService::class);
    });

    it('throws when purchase has no recurring token', function (): void {
        $chipService = Mockery::mock(ChipCollectService::class);
        $service = new RecurringService($chipService);

        $purchaseData = [
            'id' => 'purch_123',
            'client_id' => 'client_123',
            'purchase' => ['total' => 10000, 'currency' => 'MYR'],
            // No recurring_token
        ];

        expect(fn () => $service->createScheduleFromPurchase(
            $purchaseData,
            RecurringInterval::Monthly
        ))->toThrow(AIArmada\Chip\Exceptions\NoRecurringTokenException::class);
    });
});
