<?php

declare(strict_types=1);

use AIArmada\FilamentSeating\Resources\SeatMapResource;

it('has correct navigation group', function (): void {
    expect(SeatMapResource::getNavigationGroup())->toBe('Venue');
});

it('has correct navigation sort', function (): void {
    expect(SeatMapResource::getNavigationSort())->toBe(1);
});
