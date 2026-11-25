<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AttributionTouch;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionDistribution;
use Carbon\CarbonImmutable;

function makeTouch(string $affiliateId, string $timestamp): AttributionTouch
{
    return new AttributionTouch($affiliateId, CarbonImmutable::parse($timestamp));
}

test('last touch attribution favors most recent', function (): void {
    config(['affiliates.tracking.attribution_model' => 'last_touch']);
    $model = app(AttributionDistribution::class);
    $weights = $model->distribute([
        makeTouch('A1', '2024-01-01 00:00:00'),
        makeTouch('A2', '2024-02-01 00:00:00'),
    ]);

    expect($weights)->toBe(['A2' => 1.0]);
});

test('first touch attribution favors earliest', function (): void {
    config(['affiliates.tracking.attribution_model' => 'first_touch']);
    $model = app(AttributionDistribution::class);
    $weights = $model->distribute([
        makeTouch('A1', '2024-02-01 00:00:00'),
        makeTouch('A2', '2024-03-01 00:00:00'),
    ]);

    expect($weights)->toBe(['A1' => 1.0]);
});

test('linear attribution splits evenly', function (): void {
    config(['affiliates.tracking.attribution_model' => 'linear']);
    $model = app(AttributionDistribution::class);
    $weights = $model->distribute([
        makeTouch('A1', '2024-01-01 00:00:00'),
        makeTouch('A2', '2024-02-01 00:00:00'),
        makeTouch('A1', '2024-03-01 00:00:00'),
    ]);

    expect(round($weights['A1'], 2))->toBe(round(2 / 3, 2))
        ->and(round($weights['A2'], 2))->toBe(round(1 / 3, 2));
});

test('attribution strategies handle no touchpoints without a fallback weight', function (): void {
    foreach (['first_touch', 'last_touch', 'linear'] as $strategy) {
        expect(app(AttributionDistribution::class)->distribute([], $strategy))->toBe([]);
    }
});
