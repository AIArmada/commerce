<?php

declare(strict_types=1);

use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\Addressing\Features\AddressLifecycle\Traits\HasAddresses;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Venues\Models\EventFacility;
use AIArmada\Events\Features\Venues\Models\EventLocation;
use AIArmada\Events\Features\Venues\Models\FacilityType;
use AIArmada\Events\Features\Venues\Models\Venue;
use AIArmada\Events\Features\Venues\Models\VenueFacility;
use AIArmada\Events\Features\Venues\Models\VenueSpace;
use AIArmada\Events\Features\Venues\Models\VenueSpaceType;

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
    $digitalVenue = Venue::factory()->create([
        'name' => 'Digital Venue',
        'venue_type' => 'online',
    ]);

    $physicalVenue = Venue::factory()->create([
        'name' => 'Physical Venue',
        'venue_type' => 'physical',
    ]);

    $address = Address::create([
        'line1' => '1 Canonical Way',
        'line2' => 'Level 2',
        'city' => 'Kuala Lumpur',
        'state' => 'Wilayah Persekutuan',
        'postcode' => '50450',
        'country_code' => 'MY',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'google_maps_url' => 'https://maps.example/canonical',
        'waze_url' => 'https://waze.example/canonical',
        'metadata' => ['directions' => 'Enter through the north gate.'],
    ]);

    $physicalVenue->attachAddress($address, type: 'primary', isPrimary: true);

    $physicalAddress = $physicalVenue->primaryAddress();

    expect($physicalAddress)->not->toBeNull()
        ->and($physicalAddress?->line1)->toBe('1 Canonical Way')
        ->and($physicalAddress?->line2)->toBe('Level 2')
        ->and($physicalAddress?->city)->toBe('Kuala Lumpur')
        ->and($physicalAddress?->state)->toBe('Wilayah Persekutuan')
        ->and($physicalAddress?->postcode)->toBe('50450')
        ->and($physicalAddress?->country_code)->toBe('MY')
        ->and($physicalAddress?->latitude)->toBe(3.139)
        ->and($physicalAddress?->longitude)->toBe(101.6869)
        ->and($physicalAddress?->google_maps_url)->toBe('https://maps.example/canonical')
        ->and($physicalAddress?->waze_url)->toBe('https://waze.example/canonical')
        ->and($physicalAddress?->metadata['directions'] ?? null)->toBe('Enter through the north gate.')
        ->and($digitalVenue->primaryAddress())->toBeNull()
        ->and($digitalVenue->addresses()->count())->toBe(0);
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
