<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\BuildAddressNavigationLinksAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;

beforeEach(function (): void {
    $this->action = app(BuildAddressNavigationLinksAction::class);
});

it('uses manual google_maps_url over generated', function (): void {
    $address = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'KL',
        'countryCode' => 'MY',
        'latitude' => 3.1712,
        'longitude' => 101.6678,
        'google_maps_url' => 'https://maps.app.goo.gl/manual',
    ]);

    $links = $this->action->execute($address);

    expect($links['google_maps_url'])->toBe('https://maps.app.goo.gl/manual');
    expect($links['google_maps_source'])->toBe('manual');
});

it('uses manual waze_url over generated', function (): void {
    $address = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'KL',
        'countryCode' => 'MY',
        'latitude' => 3.1712,
        'longitude' => 101.6678,
        'waze_url' => 'https://waze.com/ul/manual',
    ]);

    $links = $this->action->execute($address);

    expect($links['waze_url'])->toBe('https://waze.com/ul/manual');
    expect($links['waze_source'])->toBe('manual');
});

it('uses navigation_links.google_maps.url when direct url absent', function (): void {
    $address = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'KL',
        'countryCode' => 'MY',
        'navigationLinks' => ['google_maps' => ['url' => 'https://maps.app.goo.gl/nav-link']],
    ]);

    $links = $this->action->execute($address);

    expect($links['google_maps_url'])->toBe('https://maps.app.goo.gl/nav-link');
    expect($links['google_maps_source'])->toBe('navigation_links');
});

it('uses navigation_links.waze.url when direct url absent', function (): void {
    $address = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'KL',
        'countryCode' => 'MY',
        'navigationLinks' => ['waze' => ['url' => 'https://waze.com/ul/nav-link']],
    ]);

    $links = $this->action->execute($address);

    expect($links['waze_url'])->toBe('https://waze.com/ul/nav-link');
    expect($links['waze_source'])->toBe('navigation_links');
});

it('generates google maps url from coordinates', function (): void {
    $address = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'KL',
        'countryCode' => 'MY',
        'latitude' => 3.1712,
        'longitude' => 101.6678,
    ]);

    $links = $this->action->execute($address);

    expect($links['google_maps_url'])->toContain('https://www.google.com/maps/search/');
    expect($links['google_maps_url'])->toContain('query=3.1712%2C101.6678');
    expect($links['google_maps_source'])->toBe('generated_coordinates');
});

it('generates google maps url from formatted address', function (): void {
    $address = AddressData::from([
        'formatted' => '123 Main St, KL, 50450, Malaysia',
    ]);

    $links = $this->action->execute($address);

    expect($links['google_maps_url'])->toContain('https://www.google.com/maps/search/');
    expect($links['google_maps_url'])->toContain(urlencode('123 Main St, KL, 50450, Malaysia'));
    expect($links['google_maps_source'])->toBe('generated_formatted_address');
});

it('generates waze url from coordinates', function (): void {
    $address = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'KL',
        'countryCode' => 'MY',
        'latitude' => 3.1712,
        'longitude' => 101.6678,
    ]);

    $links = $this->action->execute($address);

    expect($links['waze_url'])->toContain('https://waze.com/ul?');
    expect($links['waze_url'])->toContain('ll=3.1712%2C101.6678');
    expect($links['waze_url'])->toContain('navigate=yes');
    expect($links['waze_source'])->toBe('generated_coordinates');
});

it('generates waze url from formatted address', function (): void {
    $address = AddressData::from([
        'formatted' => '123 Main St, KL, Malaysia',
    ]);

    $links = $this->action->execute($address);

    expect($links['waze_url'])->toContain('https://waze.com/ul?');
    expect($links['waze_url'])->toContain('q=' . urlencode('123 Main St, KL, Malaysia'));
    expect($links['waze_source'])->toBe('generated_formatted_address');
});

it('generates google maps url from place id with query and query_place_id', function (): void {
    $address = AddressData::from([
        'line1' => '123 Main St',
        'city' => 'KL',
        'countryCode' => 'MY',
        'latitude' => 3.1712,
        'longitude' => 101.6678,
        'provider' => 'google',
        'provider_place_id' => 'ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4',
    ]);

    $links = $this->action->execute($address);

    expect($links['google_maps_url'])->toContain('https://www.google.com/maps/search/?');
    expect($links['google_maps_url'])->toContain('api=1');
    expect($links['google_maps_url'])->toContain(urlencode('ChIJc6C6R_Ei2jERtP6Y3Y6Y3Y4'));
    expect($links['google_maps_url'])->toContain('query_place_id=');
    expect($links['google_maps_source'])->toBe('generated_place_id');
});

it('accepts Address model and builds links', function (): void {
    $address = Address::create([
        'line1' => '123 Main St',
        'country_code' => 'MY',
        'latitude' => 3.1712,
        'longitude' => 101.6678,
    ]);

    $links = $this->action->execute($address);

    expect($links['google_maps_url'])->toContain('https://www.google.com/maps/search/');
    expect($links['waze_url'])->toContain('https://waze.com/ul?');
});

it('returns links key with navigation_links data', function (): void {
    $address = AddressData::from([
        'line1' => '123 Main St',
        'navigation_links' => ['grab' => ['url' => 'https://grab.com/dir']],
    ]);

    $links = $this->action->execute($address);

    expect($links['links'])->toBe(['grab' => ['url' => 'https://grab.com/dir']]);
});

it('returns null when no usable data exists', function (): void {
    $address = AddressData::from([
        'line1' => 'Unknown',
    ]);

    $links = $this->action->execute($address);

    expect($links['google_maps_url'])->toBeNull();
    expect($links['waze_url'])->toBeNull();
    expect($links['google_maps_source'])->toBeNull();
    expect($links['waze_source'])->toBeNull();
});
