<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ValidateAddressAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\AddressCountry;

it('passes a complete address', function (): void {
    $violations = app(ValidateAddressAction::class)->execute(AddressData::from([
        'line1' => '1 Jalan Ampang',
        'city' => 'Kuala Lumpur',
        'state' => 'Kuala Lumpur',
        'postcode' => '50450',
        'countryCode' => 'MY',
    ]));

    expect($violations)->toBe([]);
});

it('reports every missing required field', function (): void {
    $violations = app(ValidateAddressAction::class)->execute(AddressData::from([
        'countryCode' => 'MY',
    ]));

    expect($violations)->toHaveKeys(['line1', 'city', 'state', 'postcode'])
        ->and($violations['postcode'])->toContain('required')
        ->and($violations['line1'])->toContain('address line 1');
});

it('rejects postcodes outside the country pattern', function (): void {
    $violations = app(ValidateAddressAction::class)->execute(AddressData::from([
        'line1' => '1 Jalan Ampang',
        'city' => 'Kuala Lumpur',
        'state' => 'Kuala Lumpur',
        'postcode' => 'ABCDE',
        'countryCode' => 'MY',
    ]));

    expect($violations)->toBe(['postcode' => 'The postcode [ABCDE] is not a valid MY postcode.']);
});

it('accepts stored identifiers for city and state', function (): void {
    $violations = app(ValidateAddressAction::class)->execute(AddressData::from([
        'line1' => '1 Jalan Ampang',
        'cityId' => '01JTESTCITY00000000000001',
        'stateId' => '01JTESTSTATE0000000000001',
        'postcode' => '50450',
        'countryCode' => 'MY',
    ]));

    expect($violations)->toBe([]);
});

it('checks supplied postcodes even where optional', function (): void {
    $action = app(ValidateAddressAction::class);

    $without = $action->execute(AddressData::from([
        'line1' => '1 Main Street',
        'city' => 'Dublin',
        'countryCode' => 'IE',
    ]));

    $withBad = $action->execute(AddressData::from([
        'line1' => '1 Main Street',
        'city' => 'Dublin',
        'postcode' => 'AB',
        'countryCode' => 'IE',
    ]));

    $withExcludedLetters = $action->execute(AddressData::from([
        'line1' => '1 Main Street',
        'city' => 'Dublin',
        'postcode' => 'ZZZ',
        'countryCode' => 'IE',
    ]));

    expect($without)->toBe([])
        ->and($withBad)->toHaveKey('postcode')
        ->and($withExcludedLetters)->toHaveKey('postcode');
});

it('passes countries without a profile', function (): void {
    AddressCountry::query()->create(['iso2' => 'MY', 'name' => 'Malaysia']);
    AddressCountry::query()->create(['iso2' => 'AQ', 'name' => 'Antarctica']);

    $violations = app(ValidateAddressAction::class)->execute(AddressData::from([
        'countryCode' => 'AQ',
    ]));

    expect($violations)->toBe([]);
});

it('passes unknown codes when countries are unseeded', function (): void {
    $violations = app(ValidateAddressAction::class)->execute(AddressData::from([
        'countryCode' => 'XX',
    ]));

    expect($violations)->toBe([]);
});

it('rejects unknown codes once countries are seeded', function (): void {
    AddressCountry::query()->create(['iso2' => 'MY', 'name' => 'Malaysia']);

    $violations = app(ValidateAddressAction::class)->execute(AddressData::from([
        'line1' => '1 Jalan Ampang',
        'countryCode' => 'XX',
    ]));

    expect($violations)->toBe(['countryCode' => 'Unknown country code [XX].']);
});

it('passes addresses without a country code', function (): void {
    $violations = app(ValidateAddressAction::class)->execute(AddressData::from([
        'line1' => '1 Jalan Ampang',
    ]));

    expect($violations)->toBe([]);
});

it('matches patterns case-insensitively', function (): void {
    $violations = app(ValidateAddressAction::class)->execute(AddressData::from([
        'line1' => 'Dam 1',
        'city' => 'Amsterdam',
        'postcode' => '1011 ab',
        'countryCode' => 'nl',
    ]));

    expect($violations)->toBe([]);
});

it('accepts outward and full British postcodes', function (string $postcode): void {
    $violations = app(ValidateAddressAction::class)->execute(AddressData::from([
        'line1' => '1 High Street',
        'city' => 'Aberdeen',
        'postcode' => $postcode,
        'countryCode' => 'GB',
    ]));

    expect($violations)->toBe([]);
})->with([
    'outward' => ['AB10'],
    'full' => ['AB10 1AB'],
]);

it('validates adjudicated single-code and extended formats', function (string $countryCode, string $good, string $bad): void {
    $action = app(ValidateAddressAction::class);

    $base = ['line1' => 'Station', 'city' => 'Wake', 'state' => 'Wake Island'];

    $pass = $action->execute(AddressData::from($base + ['postcode' => $good, 'countryCode' => $countryCode]));
    $fail = $action->execute(AddressData::from($base + ['postcode' => $bad, 'countryCode' => $countryCode]));

    expect($pass)->not->toHaveKey('postcode')
        ->and($fail)->toHaveKey('postcode');
})->with([
    'UM single code' => ['UM', '96898', '96899'],
    'SO region format' => ['SO', 'JH 09010', '12345'],
    'AF six-digit zones' => ['AF', '100101', 'ABC'],
    'AF published four-digit' => ['AF', '1001', 'AB'],
    'PY six-digit' => ['PY', '001001', 'ABC'],
    'PY legacy four-digit' => ['PY', '0010', 'AB'],
    'MU island prefixes' => ['MU', 'R1301', 'X1301'],
    'TT six-digit' => ['TT', '500234', 'ABC'],
]);

it('profiles country names through seeded rows', function (): void {
    $this->seedCountry('MY');

    $action = app(ValidateAddressAction::class);

    $missing = $action->execute(AddressData::from(['line1' => 'Station', 'countryCode' => 'Malaysia']));
    $complete = $action->execute(AddressData::from(['line1' => 'Station', 'city' => 'Ampang', 'state' => 'Selangor', 'postcode' => '68000', 'countryCode' => 'malaysia']));

    expect($missing)->toHaveKey('city')
        ->and($missing['city'])->toContain('MY')
        ->and($complete)->toBe([]);
});
