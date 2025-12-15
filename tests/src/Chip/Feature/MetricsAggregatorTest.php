<?php

declare(strict_types=1);

use AIArmada\Chip\Services\MetricsAggregator;

describe('MetricsAggregator without database', function (): void {
    it('can be instantiated', function (): void {
        $aggregator = new MetricsAggregator;
        expect($aggregator)->toBeInstanceOf(MetricsAggregator::class);
    });
});
