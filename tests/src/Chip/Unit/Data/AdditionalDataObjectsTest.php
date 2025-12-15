<?php

declare(strict_types=1);

use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\RevenueMetrics;
use AIArmada\Chip\Data\TransactionMetrics;
use AIArmada\Chip\Data\WebhookHealth;
use AIArmada\Chip\Data\WebhookResult;

describe('RevenueMetrics data object', function (): void {
    it('can be constructed', function (): void {
        $metrics = new RevenueMetrics(
            grossRevenue: 100000,
            refunds: 5000,
            netRevenue: 95000,
            transactionCount: 10,
            averageTransaction: 10000.0,
            growthRate: 15.5,
            currency: 'MYR',
        );

        expect($metrics->grossRevenue)->toBe(100000)
            ->and($metrics->refunds)->toBe(5000)
            ->and($metrics->netRevenue)->toBe(95000)
            ->and($metrics->transactionCount)->toBe(10)
            ->and($metrics->averageTransaction)->toBe(10000.0)
            ->and($metrics->growthRate)->toBe(15.5)
            ->and($metrics->currency)->toBe('MYR');
    });

    it('formats gross revenue', function (): void {
        $metrics = new RevenueMetrics(
            grossRevenue: 100000,
            refunds: 0,
            netRevenue: 100000,
            transactionCount: 10,
            averageTransaction: 10000.0,
            growthRate: 0.0,
            currency: 'MYR',
        );

        expect($metrics->grossRevenueFormatted())->toBe('1,000.00 MYR');
    });

    it('formats net revenue', function (): void {
        $metrics = new RevenueMetrics(
            grossRevenue: 100000,
            refunds: 5000,
            netRevenue: 95000,
            transactionCount: 10,
            averageTransaction: 9500.0,
            growthRate: 0.0,
            currency: 'USD',
        );

        expect($metrics->netRevenueFormatted())->toBe('950.00 USD');
    });

    it('formats average transaction', function (): void {
        $metrics = new RevenueMetrics(
            grossRevenue: 100000,
            refunds: 0,
            netRevenue: 100000,
            transactionCount: 5,
            averageTransaction: 20000.0,
            growthRate: 0.0,
            currency: 'MYR',
        );

        expect($metrics->averageTransactionFormatted())->toBe('200.00 MYR');
    });

    it('checks positive growth', function (): void {
        $positive = new RevenueMetrics(
            grossRevenue: 100000,
            refunds: 0,
            netRevenue: 100000,
            transactionCount: 10,
            averageTransaction: 10000.0,
            growthRate: 5.0,
            currency: 'MYR',
        );

        $negative = new RevenueMetrics(
            grossRevenue: 100000,
            refunds: 0,
            netRevenue: 100000,
            transactionCount: 10,
            averageTransaction: 10000.0,
            growthRate: -2.5,
            currency: 'MYR',
        );

        expect($positive->hasPositiveGrowth())->toBeTrue()
            ->and($negative->hasPositiveGrowth())->toBeFalse();
    });
});

describe('TransactionMetrics data object', function (): void {
    it('can be constructed', function (): void {
        $metrics = new TransactionMetrics(
            total: 100,
            successful: 95,
            failed: 3,
            pending: 2,
            refunded: 0,
            successRate: 95.0,
        );

        expect($metrics->total)->toBe(100)
            ->and($metrics->successful)->toBe(95)
            ->and($metrics->failed)->toBe(3)
            ->and($metrics->pending)->toBe(2)
            ->and($metrics->successRate)->toBe(95.0);
    });

    it('checks if healthy', function (): void {
        $healthy = new TransactionMetrics(
            total: 100,
            successful: 96,
            failed: 2,
            pending: 2,
            refunded: 0,
            successRate: 96.0,
        );

        $unhealthy = new TransactionMetrics(
            total: 100,
            successful: 90,
            failed: 8,
            pending: 2,
            refunded: 0,
            successRate: 90.0,
        );

        expect($healthy->isHealthy())->toBeTrue()
            ->and($unhealthy->isHealthy())->toBeFalse();
    });

    it('calculates failure rate', function (): void {
        $metrics = new TransactionMetrics(
            total: 100,
            successful: 90,
            failed: 10,
            pending: 0,
            refunded: 0,
            successRate: 90.0,
        );

        expect($metrics->failureRate())->toBe(10.0);

        $empty = new TransactionMetrics(
            total: 0,
            successful: 0,
            failed: 0,
            pending: 0,
            refunded: 0,
            successRate: 0.0,
        );

        expect($empty->failureRate())->toBe(0.0);
    });
});

describe('DashboardMetrics data object', function (): void {
    it('can be constructed', function (): void {
        $revenue = new RevenueMetrics(
            grossRevenue: 100000,
            refunds: 0,
            netRevenue: 100000,
            transactionCount: 10,
            averageTransaction: 10000.0,
            growthRate: 5.0,
            currency: 'MYR',
        );

        $transactions = new TransactionMetrics(
            total: 100,
            successful: 95,
            failed: 3,
            pending: 2,
            refunded: 0,
            successRate: 95.0,
        );

        $metrics = new DashboardMetrics(
            revenue: $revenue,
            transactions: $transactions,
            paymentMethods: [
                ['method' => 'fpx', 'attempts' => 50, 'successful' => 48, 'success_rate' => 96.0, 'revenue' => 50000],
            ],
            failures: [
                ['reason' => 'insufficient_funds', 'count' => 2, 'lost_revenue' => 5000],
            ],
        );

        expect($metrics->revenue)->toBe($revenue)
            ->and($metrics->transactions)->toBe($transactions)
            ->and($metrics->paymentMethods)->toHaveCount(1)
            ->and($metrics->failures)->toHaveCount(1);
    });
});

describe('WebhookHealth data object', function (): void {
    it('can be created from stats', function (): void {
        $health = WebhookHealth::fromStats(
            total: 100,
            processed: 95,
            failed: 3,
            pending: 2,
            avgProcessingTimeMs: 50.0,
        );

        expect($health->total)->toBe(100)
            ->and($health->processed)->toBe(95)
            ->and($health->failed)->toBe(3)
            ->and($health->pending)->toBe(2)
            ->and($health->successRate)->toBe(95.0)
            ->and($health->avgProcessingTimeMs)->toBe(50.0)
            ->and($health->isHealthy)->toBeTrue();
    });

    it('marks unhealthy when success rate is low', function (): void {
        $health = WebhookHealth::fromStats(
            total: 100,
            processed: 90,
            failed: 10,
            pending: 0,
        );

        expect($health->isHealthy)->toBeFalse();
    });

    it('marks unhealthy when too many pending', function (): void {
        $health = WebhookHealth::fromStats(
            total: 200,
            processed: 100,
            failed: 0,
            pending: 100,
        );

        expect($health->isHealthy)->toBeFalse();
    });

    it('handles zero total gracefully', function (): void {
        $health = WebhookHealth::fromStats(
            total: 0,
            processed: 0,
            failed: 0,
            pending: 0,
        );

        expect($health->successRate)->toBe(100.0)
            ->and($health->isHealthy)->toBeTrue();
    });

    it('calculates failure rate', function (): void {
        $health = WebhookHealth::fromStats(
            total: 100,
            processed: 90,
            failed: 10,
            pending: 0,
        );

        expect($health->failureRate())->toBe(10.0);

        $empty = WebhookHealth::fromStats(
            total: 0,
            processed: 0,
            failed: 0,
            pending: 0,
        );

        expect($empty->failureRate())->toBe(0.0);
    });
});

describe('WebhookResult data object', function (): void {
    it('can create handled result', function (): void {
        $result = WebhookResult::handled('Payment processed');

        expect($result->success)->toBeTrue()
            ->and($result->handled)->toBeTrue()
            ->and($result->message)->toBe('Payment processed')
            ->and($result->isSuccess())->toBeTrue()
            ->and($result->isHandled())->toBeTrue()
            ->and($result->isSkipped())->toBeFalse()
            ->and($result->isFailed())->toBeFalse();
    });

    it('can create handled with default message', function (): void {
        $result = WebhookResult::handled();

        expect($result->message)->toBe('Webhook handled successfully');
    });

    it('can create skipped result', function (): void {
        $result = WebhookResult::skipped('No handler for event');

        expect($result->success)->toBeTrue()
            ->and($result->handled)->toBeFalse()
            ->and($result->message)->toBe('No handler for event')
            ->and($result->isSuccess())->toBeTrue()
            ->and($result->isHandled())->toBeFalse()
            ->and($result->isSkipped())->toBeTrue()
            ->and($result->isFailed())->toBeFalse();
    });

    it('can create failed result', function (): void {
        $result = WebhookResult::failed('Signature verification failed', ['error_code' => 'INVALID_SIGNATURE']);

        expect($result->success)->toBeFalse()
            ->and($result->handled)->toBeFalse()
            ->and($result->message)->toBe('Signature verification failed')
            ->and($result->meta['error_code'])->toBe('INVALID_SIGNATURE')
            ->and($result->isSuccess())->toBeFalse()
            ->and($result->isHandled())->toBeFalse()
            ->and($result->isSkipped())->toBeFalse()
            ->and($result->isFailed())->toBeTrue();
    });
});

describe('EnrichedWebhookPayload data object', function (): void {
    it('can be created from payload', function (): void {
        $payload = [
            'id' => 'purch_123',
            'client_id' => 'client_abc',
            'status' => 'paid',
            'created' => '2024-01-15T10:00:00Z',
        ];

        $enriched = EnrichedWebhookPayload::fromPayload('purchase.paid', $payload);

        expect($enriched->event)->toBe('purchase.paid')
            ->and($enriched->rawPayload)->toBe($payload)
            ->and($enriched->purchaseId)->toBe('purch_123')
            ->and($enriched->clientId)->toBe('client_abc')
            ->and($enriched->receivedAt)->toBeInstanceOf(Illuminate\Support\Carbon::class)
            ->and($enriched->eventTimestamp)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    });

    it('handles nested data structure', function (): void {
        $payload = [
            'data' => [
                'id' => 'purch_nested',
                'client_id' => 'client_nested',
            ],
        ];

        $enriched = EnrichedWebhookPayload::fromPayload('purchase.paid', $payload);

        expect($enriched->purchaseId)->toBe('purch_nested')
            ->and($enriched->clientId)->toBe('client_nested');
    });

    it('handles created_on timestamp format', function (): void {
        $payload = [
            'id' => 'purch_123',
            'created_on' => time(),
        ];

        $enriched = EnrichedWebhookPayload::fromPayload('purchase.paid', $payload);

        expect($enriched->eventTimestamp)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    });

    it('checks for local purchase and owner', function (): void {
        $enriched = new EnrichedWebhookPayload(
            event: 'purchase.paid',
            rawPayload: [],
            localPurchase: null,
            owner: null,
        );

        expect($enriched->hasLocalPurchase())->toBeFalse()
            ->and($enriched->hasOwner())->toBeFalse();
    });

    it('can get values from raw payload', function (): void {
        $payload = [
            'id' => 'purch_123',
            'nested' => [
                'value' => 'test',
            ],
        ];

        $enriched = EnrichedWebhookPayload::fromPayload('purchase.paid', $payload);

        expect($enriched->get('id'))->toBe('purch_123')
            ->and($enriched->get('nested.value'))->toBe('test')
            ->and($enriched->get('missing', 'default'))->toBe('default');
    });
});
