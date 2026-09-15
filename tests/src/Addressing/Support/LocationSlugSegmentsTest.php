<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\LocationSlugSegments;

beforeEach(function (): void {
    $this->country = AddressCountry::query()->create(['iso2' => 'MY', 'name' => 'Malaysia']);
    $this->state = State::query()->create([
        'country_id' => $this->country->getKey(),
        'name' => 'Wilayah Persekutuan',
    ]);
    $this->city = City::query()->create([
        'country_id' => $this->country->getKey(),
        'state_id' => $this->state->getKey(),
        'name' => 'Kuala Lumpur',
    ]);
    $this->area = AddressArea::query()->create([
        'country_id' => $this->country->getKey(),
        'country_code' => 'MY',
        'type' => 'locality',
        'level' => 2,
        'name' => 'Bangsar',
        'slug' => 'bangsar',
        'source' => 'tests',
        'source_id' => 'bangsar',
    ]);
});

it('builds a city-state-country suffix from literal strings', function (): void {
    $suffix = LocationSlugSegments::suffix([
        'city' => 'Kuala Lumpur',
        'state' => 'Wilayah Persekutuan',
        'country_code' => 'MY',
    ]);

    expect($suffix)->toBe('kuala-lumpur-wilayah-persekutuan-my');
});

it('falls back to canonical reference names for ids', function (): void {
    $suffix = LocationSlugSegments::suffix([
        'city_id' => $this->city->getKey(),
        'state_id' => $this->state->getKey(),
        'country_id' => $this->country->getKey(),
    ]);

    expect($suffix)->toBe('kuala-lumpur-wilayah-persekutuan-my');
});

it('falls back to assigned area names and collapses duplicates', function (): void {
    $suffix = LocationSlugSegments::suffix(
        ['country_id' => $this->country->getKey()],
        (string) $this->area->getKey(),
        (string) $this->area->getKey(),
    );

    expect($suffix)->toBe('bangsar-my');
});

it('returns an empty suffix without location data', function (): void {
    expect(LocationSlugSegments::suffix([]))->toBe('');
});

it('ignores non-string area ids in the suffix', function (): void {
    expect(LocationSlugSegments::suffix(['country_code' => 'MY'], 123, ['not-a-string']))->toBe('my');
});

it('lets the suffix prefer the referenced country over the literal', function (): void {
    $address = ['country_id' => $this->country->getKey(), 'country_code' => 'SG'];

    expect(LocationSlugSegments::suffix($address))->toBe('sg')
        ->and(LocationSlugSegments::suffix($address, preferLiteralCountry: false))->toBe('my');
});

it('resolves granular names and ignores non-uuid input', function (): void {
    expect(LocationSlugSegments::areaName($this->area->getKey()))->toBe('Bangsar')
        ->and(LocationSlugSegments::cityName($this->city->getKey()))->toBe('Kuala Lumpur')
        ->and(LocationSlugSegments::stateName($this->state->getKey()))->toBe('Wilayah Persekutuan')
        ->and(LocationSlugSegments::areaName('not-a-uuid'))->toBeNull()
        ->and(LocationSlugSegments::cityName(123))->toBeNull();
});

it('resolves country codes with configurable literal preference', function (): void {
    $address = ['country_id' => $this->country->getKey(), 'country_code' => 'SG'];

    expect(LocationSlugSegments::countryCode($address, true))->toBe('SG')
        ->and(LocationSlugSegments::countryCode($address))->toBe('MY')
        ->and(LocationSlugSegments::countryCode([]))->toBeNull();
});
