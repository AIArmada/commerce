<?php

declare(strict_types=1);

use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Data\RevenueMetrics;
use AIArmada\Chip\Data\TransactionMetrics;
use AIArmada\Chip\Services\LocalAnalyticsService;
use Carbon\CarbonImmutable;

describe('LocalAnalyticsService', function (): void {
    beforeEach(function (): void {
        $this->service = new LocalAnalyticsService;
        $this->startDate = CarbonImmutable::now()->subDays(30);
        $this->endDate = CarbonImmutable::now();
    });

    describe('instantiation', function (): void {
        it('can be instantiated', function (): void {
            expect($this->service)->toBeInstanceOf(LocalAnalyticsService::class);
        });
    });

    describe('method signatures', function (): void {
        it('getDashboardMetrics returns DashboardMetrics', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getDashboardMetrics');
            expect($reflection->getReturnType()->getName())->toBe(DashboardMetrics::class);
        });

        it('getRevenueMetrics returns RevenueMetrics', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getRevenueMetrics');
            expect($reflection->getReturnType()->getName())->toBe(RevenueMetrics::class);
        });

        it('getTransactionMetrics returns TransactionMetrics', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getTransactionMetrics');
            expect($reflection->getReturnType()->getName())->toBe(TransactionMetrics::class);
        });

        it('getPaymentMethodBreakdown returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getPaymentMethodBreakdown');
            expect($reflection->getReturnType()->getName())->toBe('array');
        });

        it('getFailureAnalysis returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getFailureAnalysis');
            expect($reflection->getReturnType()->getName())->toBe('array');
        });

        it('getRevenueTrend returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getRevenueTrend');
            expect($reflection->getReturnType()->getName())->toBe('array');
        });

    });
});
