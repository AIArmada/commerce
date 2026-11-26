<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressAreaDeletionGuard;
use AIArmada\Addressing\Contracts\AddressCountryDeletionGuard;
use AIArmada\Addressing\Contracts\GoogleMapsInputNormalizer;
use AIArmada\Addressing\Contracts\GooglePlaceSelectionResolver;
use AIArmada\Addressing\GooglePlaces\GoogleMapsInputValues;
use AIArmada\Addressing\GooglePlaces\GooglePlaceIdentity;
use AIArmada\Addressing\GooglePlaces\GooglePlacesConfiguration;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Rules\ValidAreaAssignmentRoles;
use AIArmada\Addressing\Support\AddressAssignments;
use AIArmada\Addressing\Support\AddressFormState;
use AIArmada\Addressing\Support\AddressHierarchyFormatter;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

test('guard address country and area deletion actions prevent deleting referenced records', function (): void {
    $country = AddressCountry::query()->create([
        'name' => 'Malaysia Test',
        'iso2' => 'MY',
        'iso3' => 'MYS',
        'numeric_code' => '458',
    ]);

    $parentArea = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'type' => 'administrative_district',
        'level' => 2,
        'name' => 'Petaling',
        'code' => 'MY-PTG',
        'slug' => 'petaling',
        'source' => 'test',
        'source_id' => 'test-my-ptg',
        'is_active' => true,
    ]);

    $childArea = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'parent_id' => $parentArea->getKey(),
        'type' => 'administrative_subdivision',
        'level' => 3,
        'name' => 'Damansara',
        'code' => 'MY-DMS',
        'slug' => 'damansara',
        'source' => 'test',
        'source_id' => 'test-my-dms',
        'is_active' => true,
    ]);

    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $parentArea->getKey(),
        'child_address_area_id' => $childArea->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
    ]);

    expect(fn () => app(AddressCountryDeletionGuard::class)->handle((string) $2->getKey()))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(AddressAreaDeletionGuard::class)->handle((string) $2->getKey()))
        ->toThrow(ValidationException::class);

    expect(fn () => app(AddressAreaDeletionGuard::class)->handle((string) $2->getKey()))
        ->not->toThrow(ValidationException::class);
});

test('valid area assignment roles rule validates role against country profile', function (): void {
    $country = AddressCountry::query()->create([
        'name' => 'Malaysia',
        'iso2' => 'MY',
        'iso3' => 'MYS',
        'numeric_code' => '458',
    ]);

    $rule = (new ValidAreaAssignmentRoles)->setData([
        'address' => ['country_id' => $country->getKey()],
    ]);

    $failures = [];
    $rule->validate('address.area_assignments', [
        'administrative_district' => 'area-uuid',
        'bogus_role' => 'area-uuid-2',
    ], function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    expect($failures)->toHaveCount(1);
});

test('address hierarchy formatter and form state normalize selections and format lines', function (): void {
    $country = AddressCountry::query()->create([
        'name' => 'Malaysia',
        'iso2' => 'MY',
        'iso3' => 'MYS',
        'numeric_code' => '458',
    ]);

    $address = Address::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'country' => 'Malaysia',
        'line1' => 'Jalan Ilmu 1',
        'postcode' => '40100',
        'city' => 'Shah Alam',
        'state' => 'Selangor',
    ]);

    expect(AddressHierarchyFormatter::displayLines($address)['street'])->toBe('Jalan Ilmu 1')
        ->and(AddressAssignments::normalize(['administrative_district' => '  ']))->toBe([])
        ->and(AddressFormState::normalizeLocationId('not-a-uuid'))->toBeNull();
});

test('google places identity, input normalization, and place selection resolution work seamlessly', function (): void {
    config()->set('addressing.google_places.enabled', true);
    config()->set('addressing.google_places.api_key', 'test-api-key');

    expect(GooglePlacesConfiguration::isEnabled())->toBeTrue()
        ->and(GoogleMapsInputValues::placeId('places/ChIJ123'))->toBe('ChIJ123');

    $identity = GooglePlaceIdentity::fromPlace([
        'id' => 'ChIJ123',
        'googleMapsUri' => 'https://maps.google.com/?cid=001188566389576577631',
        'addressComponents' => [],
    ]);

    expect($identity['google_place_id'])->toBe('ChIJ123')
        ->and($identity['google_cid'])->toBe('1188566389576577631');

    Http::fake();
    $normalized = app(GoogleMapsInputNormalizer::class)->handle([
        'google_maps_url' => 'https://www.google.com/maps/search/?api=1&query=3.1390,101.6869&query_place_id=ChIJ123',
    ]);

    expect($normalized['google_place_id'])->toBe('ChIJ123')
        ->and($normalized['google_resolution_status'])->toBe('resolved')
        ->and($normalized['lat'])->toBe(3.139);

    $country = AddressCountry::query()->create([
        'name' => 'Malaysia',
        'iso2' => 'MY',
        'iso3' => 'MYS',
        'numeric_code' => '458',
    ]);

    $resolved = app(GooglePlaceSelectionResolver::class)->handle([
        'id' => 'ChIJ123',
        'displayName' => ['text' => 'Masjid Jamek'],
        'location' => ['lat' => 3.149, 'lng' => 101.696],
        'addressComponents' => [
            ['longText' => 'Malaysia', 'shortText' => 'MY', 'types' => ['country']],
            ['longText' => 'Kuala Lumpur', 'shortText' => 'Kuala Lumpur', 'types' => ['administrative_area_level_1']],
            ['longText' => '50050', 'shortText' => '50050', 'types' => ['postal_code']],
        ],
    ]);

    expect($resolved['country_id'])->toBe($country->getKey())
        ->and($resolved['google_place_id'])->toBe('ChIJ123')
        ->and($resolved['postcode'])->toBe('50050');
});
