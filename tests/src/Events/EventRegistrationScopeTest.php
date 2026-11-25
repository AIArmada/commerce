<?php

declare(strict_types=1);

use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Data\RegistrationMode;
use AIArmada\Ticketing\Enums\PricingMode;

it('creates scope from explicit modes', function (): void {
    $scope = new EventRegistrationScopeData(
        eventId: 'event-id',
        occurrenceId: null,
        sessionId: null,
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
    $scope = new EventRegistrationScopeData(
        eventId: 'event-id',
        occurrenceId: null,
        sessionId: null,
        pricingMode: PricingMode::Free,
        registrationMode: RegistrationMode::None,
        shouldIssuePasses: true,
        capacity: null,
    );

    expect($scope->isOpenDoor())->toBeTrue();
    expect($scope->requiresRegistration())->toBeFalse();
});

it('identifies required registration scope', function (): void {
    $scope = new EventRegistrationScopeData(
        eventId: 'event-id',
        occurrenceId: null,
        sessionId: null,
        pricingMode: PricingMode::Free,
        registrationMode: RegistrationMode::Required,
        shouldIssuePasses: true,
        capacity: null,
    );

    expect($scope->requiresRegistration())->toBeTrue();
    expect($scope->isOpenDoor())->toBeFalse();
});

it('projects registration scope identifiers for persistence', function (): void {
    $scope = new EventRegistrationScopeData(
        eventId: 'event-id',
        occurrenceId: 'occurrence-id',
        sessionId: 'session-id',
        pricingMode: PricingMode::Paid,
        registrationMode: RegistrationMode::Required,
        shouldIssuePasses: false,
        capacity: 10,
    );

    expect($scope->toRegistrationData())->toBe([
        'event_id' => 'event-id',
        'event_occurrence_id' => 'occurrence-id',
        'event_session_id' => 'session-id',
    ]);
});
