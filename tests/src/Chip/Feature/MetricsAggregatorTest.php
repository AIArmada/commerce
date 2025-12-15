<?php

declare(strict_types=1);

use AIArmada\Chip\Services\MetricsAggregator;

describe('MetricsAggregator without database', function () {
    it('can be instantiated', function () {
        $aggregator = new MetricsAggregator;
        expect($aggregator)->toBeInstanceOf(MetricsAggregator::class);
    });
});
