<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Traits\HasAddresses;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Data\VenueData;
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

    $physicalData = VenueData::fromVenue($physicalVenue);
    $digitalData = VenueData::fromVenue($digitalVenue);

    expect($physicalData)->not->toBeNull()
        ->and($physicalData?->line1)->toBe('1 Canonical Way')
        ->and($physicalData?->line2)->toBe('Level 2')
        ->and($physicalData?->city)->toBe('Kuala Lumpur')
        ->and($physicalData?->state)->toBe('Wilayah Persekutuan')
        ->and($physicalData?->postcode)->toBe('50450')
        ->and($physicalData?->country_code)->toBe('MY')
        ->and($physicalData?->latitude)->toBe(3.139)
        ->and($physicalData?->longitude)->toBe(101.6869)
        ->and($physicalData?->google_maps_url)->toBe('https://maps.example/canonical')
        ->and($physicalData?->waze_url)->toBe('https://waze.example/canonical')
        ->and($physicalData?->directions)->toBe('Enter through the north gate.')
        ->and($digitalData)->not->toBeNull()
        ->and($digitalData?->line1)->toBeNull()
        ->and($digitalData?->city)->toBeNull()
        ->and($digitalData?->country_code)->toBeNull()
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
