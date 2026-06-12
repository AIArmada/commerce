<?php

declare(strict_types=1);

use AIArmada\Contacting\Support\NormalizesEmailAddress;
use AIArmada\Contacting\Support\NormalizesPhoneNumber;
use AIArmada\Contacting\Support\NormalizesSocialHandle;
use AIArmada\Contacting\Support\NormalizesUrl;

test('NormalizesEmailAddress normalizes email', function () {
    $normalizer = new NormalizesEmailAddress;

    expect($normalizer->normalize('  User@Example.COM  '))->toBe('user@example.com');
    expect($normalizer->normalize('valid@email.com'))->toBe('valid@email.com');
    expect($normalizer->normalize(null))->toBeNull();
    expect($normalizer->normalize(''))->toBeNull();
    expect($normalizer->normalize('not-an-email'))->toBeNull();
});

test('NormalizesPhoneNumber normalizes MY phone', function () {
    $normalizer = new NormalizesPhoneNumber;

    $result = $normalizer->normalize('+60123456789');
    expect($result['normalized'])->toBe('+60123456789');
    expect($result['display'])->not->toBeNull();

    $result = $normalizer->normalize(null);
    expect($result['normalized'])->toBeNull();
    expect($result['display'])->toBeNull();

    $result = $normalizer->normalize('');
    expect($result['normalized'])->toBeNull();
    expect($result['display'])->toBeNull();
});

test('NormalizesUrl normalizes URLs', function () {
    $normalizer = new NormalizesUrl;

    expect($normalizer->normalize('example.com'))->toBe('https://example.com');
    expect($normalizer->normalize('https://example.com'))->toBe('https://example.com');
    expect($normalizer->normalize(null))->toBeNull();
    expect($normalizer->normalize(''))->toBeNull();
    expect($normalizer->normalize('ftp://example.com'))->toBeNull();
});

test('NormalizesSocialHandle normalizes handles', function () {
    $normalizer = new NormalizesSocialHandle;

    expect($normalizer->normalize('@username'))->toBe('username');
    expect($normalizer->normalize('  @test  '))->toBe('test');
    expect($normalizer->normalize('plain'))->toBe('plain');
    expect($normalizer->normalize(null))->toBeNull();
    expect($normalizer->normalize(''))->toBeNull();
});
