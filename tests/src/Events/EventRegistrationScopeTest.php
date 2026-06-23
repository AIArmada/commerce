<?php

declare(strict_types=1);

use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Enums\RegistrationMode;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Support\EventRegistrationScope;

it('creates scope from explicit modes', function (): void {
    $event = Event::factory()->make();

    $scope = new EventRegistrationScope(
        event: $event,
        occurrence: null,
        session: null,
        pricingMode: PricingMode::Free,
        registrationMode: RegistrationMode::Optional,
        shouldIssuePasses: true,
        capacity: 100,
    );

    expect($scope->pricingMode)->toBe(PricingMode::Free);
    expect($scope->registrationMode)->toBe(RegistrationMode::Optional);
    expect($scope->shouldIssuePasses)->toBeTrue();
    expect($scope->capacity)->toBe(100);
    expect($scope->isFreeOnly())->toBeTrue();
    expect($scope->requiresRegistration())->toBeFalse();
    expect($scope->isOpenDoor())->toBeFalse();
});

it('identifies open door scope', function (): void {
    $event = Event::factory()->make();

    $scope = new EventRegistrationScope(
        event: $event,
        occurrence: null,
        session: null,
        pricingMode: PricingMode::Free,
        registrationMode: RegistrationMode::None,
        shouldIssuePasses: true,
        capacity: null,
    );

    expect($scope->isOpenDoor())->toBeTrue();
    expect($scope->requiresRegistration())->toBeFalse();
});

it('identifies required registration scope', function (): void {
    $event = Event::factory()->make();

    $scope = new EventRegistrationScope(
        event: $event,
        occurrence: null,
        session: null,
        pricingMode: PricingMode::Free,
        registrationMode: RegistrationMode::Required,
        shouldIssuePasses: true,
        capacity: null,
    );

    expect($scope->requiresRegistration())->toBeTrue();
    expect($scope->isOpenDoor())->toBeFalse();
});
