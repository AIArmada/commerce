<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Traits\HasAddresses;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventFacility;
use AIArmada\Events\Models\EventLocation;
use AIArmada\Events\Models\FacilityType;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Models\VenueFacility;
use AIArmada\Events\Models\VenueSpace;
use AIArmada\Events\Models\VenueSpaceType;

it('adopts HasAddresses on every venue and facility model', function (): void {
    foreach ([
        Venue::class,
        VenueSpace::class,
        VenueSpaceType::class,
        VenueFacility::class,
        EventFacility::class,
        FacilityType::class,
        EventLocation::class,
    ] as $modelClass) {
        expect(class_uses_recursive($modelClass))->toContain(HasAddresses::class);
    }
});

it('keeps physical and digital venue records address-optional while canonical writes attach addresses', function (): void {
    $venue = Venue::factory()->create([
        'name' => 'Digital Venue',
        'venue_type' => 'online',
    ]);

    expect($venue->primaryAddress())->toBeNull();

    $address = Address::create([
        'line1' => '1 Canonical Way',
        'city' => 'Kuala Lumpur',
        'country_code' => 'MY',
    ]);

    $venue->attachAddress($address, type: 'primary', isPrimary: true);

    expect($venue->primaryAddress())->not->toBeNull()
        ->and($venue->primaryAddress()?->line1)->toBe('1 Canonical Way');
});

it('keeps digital event locations address-optional and owner-safe', function (): void {
    $owner = User::query()->create([
        'name' => 'Event Address Owner',
        'email' => 'event-address-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $event = Event::factory()->create();
        $location = EventLocation::factory()->create([
            'event_id' => $event->id,
            'label' => 'Online room',
        ]);

        expect($location->venue_id)->toBeNull()
            ->and($location->venue_space_id)->toBeNull()
            ->and($location->primaryAddress())->toBeNull();

        $address = Address::create([
            'line1' => 'Event Address',
            'city' => 'Petaling Jaya',
            'country_code' => 'MY',
        ]);

        $location->attachAddress($address, type: 'primary', isPrimary: true);

        expect($location->primaryAddress()?->line1)->toBe('Event Address')
            ->and(OwnerContext::withOwner($owner, fn (): int => $location->addresses()->count()))
            ->toBe(1);
    });
});
