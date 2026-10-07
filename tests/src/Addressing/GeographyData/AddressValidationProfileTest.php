<?php

declare(strict_types=1);

use AIArmada\Addressing\Support\AddressValidationProfiles;

it('loads the curated validation profiles', function (): void {
    $profiles = AddressValidationProfiles::all();

    expect($profiles)->toHaveCount(212)
        ->and($profiles)->not->toHaveKey('IL');
});

it('uses only known address fields for required lists', function (): void {
    $allowed = ['line1', 'city', 'state', 'postcode'];

    foreach (AddressValidationProfiles::all() as $code => $profile) {
        expect($profile->required)->each->toBeIn($allowed);
        expect($profile->countryCode)->toBe($code);
    }
});

it('ships only compilable patterns', function (): void {
    foreach (AddressValidationProfiles::all() as $code => $profile) {
        if ($profile->pattern === null) {
            continue;
        }

        expect(@preg_match('/' . $profile->pattern . '/i', ''))->not->toBeFalse($code);
    }
});

it('reloads profiles after flush', function (): void {
    expect(AddressValidationProfiles::forCountry('MY'))->not->toBeNull();

    AddressValidationProfiles::flush();

    expect(AddressValidationProfiles::forCountry('MY')?->pattern)->toBe('\d{5}');
});

it('fails loudly on broken patterns', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'profiles') . '.json';
    file_put_contents($path, json_encode(['XX' => ['pattern' => '([', 'required' => [], 'upper' => []]]));

    try {
        AddressValidationProfiles::all($path);
        $this->fail('Expected a RuntimeException for the broken pattern.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Invalid postcode pattern for [XX].');
    } finally {
        unlink($path);
    }
});

it('pins the adjudicated patterns', function (string $countryCode, string $postcode): void {
    expect(AddressValidationProfiles::matchesPattern(
        AddressValidationProfiles::forCountry($countryCode)?->pattern,
        $postcode,
    ))->toBeTrue();
})->with([
    'AF six-digit' => ['AF', '100101'],
    'AF published four-digit' => ['AF', '1001'],
    'PY legacy four-digit' => ['PY', '0010'],
    'TT six-digit' => ['TT', '500234'],
    'AX prefixed' => ['AX', 'AX-22100'],
    'CA outward' => ['CA', 'K1A'],
    'CA full' => ['CA', 'K1A 0B1'],
    'GB outward' => ['GB', 'AB10'],
    'GB full' => ['GB', 'AB10 1AB'],
    'GU 96930' => ['GU', '96930'],
    'IE routing key' => ['IE', 'A41'],
    'IE full' => ['IE', 'A41 F2T4'],
    'IR prefix' => ['IR', '96914'],
    'IR full' => ['IR', '11936-12345'],
    'LB hyphenated' => ['LB', '1107-2090'],
    'LC spaced' => ['LC', 'LC01 101'],
    'LC compact' => ['LC', 'LC01101'],
    'MS bare' => ['MS', '1110'],
    'MU Rodrigues' => ['MU', 'R1301'],
    'NL digits' => ['NL', '1011'],
    'NL full' => ['NL', '1011 AB'],
    'SH Tristan' => ['SH', 'TDCU 1ZZ'],
    'UM pinned' => ['UM', '96898'],
]);
