<?php

declare(strict_types=1);

use AIArmada\Addressing\Rules\ValidPostalCode;
use Illuminate\Support\Facades\Validator;

it('validates postcodes against the country pattern', function (string $countryCode, mixed $value, bool $valid): void {
    expect(ValidPostalCode::isValid($countryCode, $value))->toBe($valid);
})->with([
    'MY good' => ['MY', '50450', true],
    'MY bad' => ['MY', 'ABCDE', false],
    'MY padded' => ['MY', '  50450  ', true],
    'GB outward' => ['GB', 'AB10', true],
    'GB full' => ['GB', 'AB10 1AB', true],
    'GB bad' => ['GB', 'XX', false],
    'UM pinned' => ['UM', '96898', true],
    'UM off-by-one' => ['UM', '96899', false],
    'MY integer' => ['MY', 50450, true],
    'MY integer bad' => ['MY', 123, false],
    'blank passes (presence is required\'s job)' => ['MY', '', true],
    'null fails (not a string)' => ['MY', null, false],
    'unknown country passes' => ['XX', 'anything', true],
]);

it('fails Laravel validation with a named message', function (): void {
    $validator = Validator::make(
        ['postcode' => 'ABCDE'],
        ['postcode' => ['required', new ValidPostalCode('MY')]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('postcode'))->toBe('The postcode is not a valid MY postcode.');
});

it('passes Laravel validation for matching codes', function (): void {
    $validator = Validator::make(
        ['postcode' => '50450'],
        ['postcode' => ['required', new ValidPostalCode('MY')]],
    );

    expect($validator->passes())->toBeTrue();
});
