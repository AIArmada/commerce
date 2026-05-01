<?php

declare(strict_types=1);

use AIArmada\Chip\Services\LocalAnalyticsService;

describe('LocalAnalyticsService', function (): void {
    it('can be instantiated', function (): void {
        $service = new LocalAnalyticsService;
        expect($service)->toBeInstanceOf(LocalAnalyticsService::class);
    });
});

