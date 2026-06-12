<?php

declare(strict_types=1);

use AIArmada\Addressing\Support\NormalizeNavigationUrl;

beforeEach(function (): void {
    $this->normalizer = new NormalizeNavigationUrl;
});

it('returns null for null input', function (): void {
    expect($this->normalizer->normalize(null))->toBeNull();
});

it('returns null for empty string', function (): void {
    expect($this->normalizer->normalize(''))->toBeNull();
});

it('trims whitespace', function (): void {
    $result = $this->normalizer->normalize('  https://maps.app.goo.gl/example  ');
    expect($result)->toBe('https://maps.app.goo.gl/example');
});

it('accepts google maps app link', function (): void {
    $result = $this->normalizer->normalize('https://maps.app.goo.gl/example');
    expect($result)->toBe('https://maps.app.goo.gl/example');
});

it('accepts google maps place link', function (): void {
    $result = $this->normalizer->normalize('https://www.google.com/maps/place/example');
    expect($result)->toBe('https://www.google.com/maps/place/example');
});

it('accepts waze link', function (): void {
    $result = $this->normalizer->normalize('https://waze.com/ul?ll=3.0738,101.5183&navigate=yes');
    expect($result)->toBe('https://waze.com/ul?ll=3.0738,101.5183&navigate=yes');
});

it('rejects javascript scheme', function (): void {
    expect($this->normalizer->normalize('javascript:alert(1)'))->toBeNull();
});

it('rejects ftp scheme', function (): void {
    expect($this->normalizer->normalize('ftp://example.com/file'))->toBeNull();
});

it('does not perform http requests', function (): void {
    $result = $this->normalizer->normalize('https://maps.app.goo.gl/example');
    expect($result)->toBe('https://maps.app.goo.gl/example');
});
