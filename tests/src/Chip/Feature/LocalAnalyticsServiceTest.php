<?php

declare(strict_types=1);

use AIArmada\Chip\Services\LocalAnalyticsService;
use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Data\RevenueMetrics;
use AIArmada\Chip\Data\TransactionMetrics;
use Illuminate\Support\Carbon;

describe('LocalAnalyticsService without database', function () {
    it('can be instantiated', function () {
        $service = new LocalAnalyticsService;
        expect($service)->toBeInstanceOf(LocalAnalyticsService::class);
    });
});
