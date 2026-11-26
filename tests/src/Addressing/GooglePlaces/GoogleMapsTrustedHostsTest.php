<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\GoogleMapsLookup;
use AIArmada\Addressing\GooglePlaces\GoogleMapsTrustedHosts;
use AIArmada\Addressing\GooglePlaces\GoogleMapsUrlParser;
use AIArmada\Addressing\GooglePlaces\GooglePlaceIdentity;
use Illuminate\Support\Facades\Http;

it('accepts documented maps, shortener, and consent hosts only', function (): void {
    foreach ([
        'google.com',
        'www.google.com',
        'maps.google.com',
        'www.google.com.my',
        'maps.google.com.my',
        'www.google.co.uk',
        'maps.app.goo.gl',
        'goo.gl',
        'g.co',
        'consent.google.com',
    ] as $host) {
        expect(GoogleMapsTrustedHosts::isSupportedHost($host))->toBeTrue($host);
    }

    foreach ([
        'google.evil.com',
        'maps.google.evil.com',
        'www.google.com.evil',
        'evilgoogle.com',
        'not-google.com',
        'google.com.evil.com',
    ] as $host) {
        expect(GoogleMapsTrustedHosts::isSupportedHost($host))->toBeFalse($host);
    }
});

it('resolves redirects only for trusted maps and shortener links', function (): void {
    $parser = new GoogleMapsUrlParser;

    expect($parser->parse('https://maps.app.goo.gl/example')['needs_redirect_resolution'])->toBeTrue()
        ->and($parser->parse('https://www.google.com/maps/search/?api=1&cid=123')['needs_redirect_resolution'])->toBeTrue()
        ->and($parser->parse('https://google.evil.com/maps/search/?api=1&cid=123')['needs_redirect_resolution'])->toBeFalse()
        ->and($parser->parse('https://evil.example/maps/search/?api=1&cid=123')['needs_redirect_resolution'])->toBeFalse();
});

it('does not unwrap consent links to untrusted hosts', function (): void {
    $parser = new GoogleMapsUrlParser;

    $consent = 'https://consent.google.com/ml?continue=https%3A%2F%2Fevil.example%2Fmaps';

    expect($parser->unwrapConsent($consent))->toBe($consent)
        ->and($parser->unwrapConsent('https://consent.google.com/ml?continue=https%3A%2F%2Fwww.google.com%2Fmaps'))
        ->toBe('https://www.google.com/maps');
});

it('extracts cids only from trusted maps links', function (): void {
    expect(GooglePlaceIdentity::cidsFromGoogleMapsUrl('https://maps.google.com/?cid=001188566389576577631'))
        ->toBe(['1188566389576577631'])
        ->and(GooglePlaceIdentity::cidsFromGoogleMapsUrl('https://google.evil.com/?cid=001188566389576577631'))
        ->toBe([]);
});

it('skips remote lookups for untrusted hosts and missing api keys', function (): void {
    Http::fake();

    config()->set('addressing.google_places.server_api_key', null);
    config()->set('services.google.places_server_api_key', null);

    $lookup = app(GoogleMapsLookup::class);

    expect($lookup->resolveUrl('https://google.evil.com/maps?cid=123'))->toBe('https://google.evil.com/maps?cid=123')
        ->and($lookup->placeDetails('ChIJ123'))->toBeNull();

    Http::assertNothingSent();
});
