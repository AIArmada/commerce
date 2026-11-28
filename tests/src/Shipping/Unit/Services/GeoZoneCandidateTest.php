<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\ZoneCandidateData;
use AIArmada\Shipping\Features\Rates\Strategies\GeoZoneResolutionStrategy;

it('matches immutable zone candidates without exposing models or accepting partial state matches', function (): void {
    $zone = new ZoneCandidateData('state', 'state', ['US'], ['CA'], null, null, null, null, 10, false, true);
    $fallback = new ZoneCandidateData('fallback', 'country', ['GB'], null, null, null, null, null, 0, true, true);
    $strategy = new GeoZoneResolutionStrategy;
    $address = fn (string $state): AddressData => new AddressData(name: 'Test', phone: '123', line1: 'Street', postcode: '12345', country: 'US', state: $state);

    expect($strategy->resolve($address('CA'), [$zone, $fallback]))->toBe(['state'])
        ->and($strategy->resolve($address('North Carolina'), [$zone, $fallback]))->toBe(['fallback']);
});
