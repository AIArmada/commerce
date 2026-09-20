<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;

it('accepts google_maps_url', function (): void {
    $data = AddressData::from(['google_maps_url' => 'https://maps.app.goo.gl/test']);
    expect($data->googleMapsUrl)->toBe('https://maps.app.goo.gl/test');
});

it('accepts googleMapsUrl', function (): void {
    $data = AddressData::from(['googleMapsUrl' => 'https://maps.app.goo.gl/test']);
    expect($data->googleMapsUrl)->toBe('https://maps.app.goo.gl/test');
});

it('accepts maps_url', function (): void {
    $data = AddressData::from(['maps_url' => 'https://maps.app.goo.gl/test']);
    expect($data->googleMapsUrl)->toBe('https://maps.app.goo.gl/test');
});

it('accepts waze_url', function (): void {
    $data = AddressData::from(['waze_url' => 'https://waze.com/ul/test']);
    expect($data->wazeUrl)->toBe('https://waze.com/ul/test');
});

it('accepts wazeUrl', function (): void {
    $data = AddressData::from(['wazeUrl' => 'https://waze.com/ul/test']);
    expect($data->wazeUrl)->toBe('https://waze.com/ul/test');
});

it('accepts navigation_links', function (): void {
    $data = AddressData::from(['navigation_links' => ['foo' => 'bar']]);
    expect($data->navigationLinks)->toBe(['foo' => 'bar']);
});

it('sets empty navigation_links to empty array', function (): void {
    $data = AddressData::from(['line1' => 'Test']);
    expect($data->navigationLinks)->toBe([]);
});

it('converts to array and back preserving nav links', function (): void {
    $original = AddressData::from([
        'line1' => '123 Main St',
        'google_maps_url' => 'https://maps.app.goo.gl/test',
        'navigation_links' => ['waze' => ['url' => 'https://waze.com/test']],
    ]);

    $array = $original->toArray();
    $restored = AddressData::from($array);

    expect($restored->googleMapsUrl)->toBe('https://maps.app.goo.gl/test');
    expect($restored->navigationLinks)->toBe(['waze' => ['url' => 'https://waze.com/test']]);
});

it('drops non-http navigation urls', function (): void {
    $data = AddressData::from([
        'google_maps_url' => 'javascript:alert(1)',
        'waze_url' => 'ftp://example.com/x',
        'navigation_links' => [
            'waze' => ['url' => 'javascript:alert(1)'],
            'foo' => 'bar',
        ],
    ]);

    expect($data->googleMapsUrl)->toBeNull();
    expect($data->wazeUrl)->toBeNull();
    expect($data->navigationLinks)->toBe(['waze' => ['url' => null], 'foo' => 'bar']);
});
