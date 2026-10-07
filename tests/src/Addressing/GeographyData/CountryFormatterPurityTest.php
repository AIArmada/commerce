<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;

function addressingFormatterClasses(): array
{
    $classes = [];

    foreach (glob(__DIR__ . '/../../../../packages/addressing/src/Geography/*/*AddressFormatter.php') ?: [] as $path) {
        $classes[basename($path, '.php')] = 'AIArmada\\Addressing\\Geography\\' . basename(dirname($path)) . '\\' . basename($path, '.php');
    }

    ksort($classes);

    return $classes;
}

it('prints full addresses without blank lines', function (string $formatter): void {
    $output = app($formatter)->format(AddressData::from([
        'line1' => 'Line One',
        'line2' => 'Line Two',
        'line3' => 'Line Three',
        'city' => 'Testville',
        'state' => 'Test State',
        'postcode' => '12345',
        'countryCode' => $formatter::countryCode(),
    ]));

    expect($output)->not->toBe('')
        ->and($output)->not->toContain("\n\n")
        ->and(mb_trim($output))->toBe($output);
})->with(addressingFormatterClasses());

it('prints country-only addresses without blank lines', function (string $formatter): void {
    $output = app($formatter)->format(AddressData::from([
        'countryCode' => $formatter::countryCode(),
    ]));

    expect($output)->not->toContain("\n\n")
        ->and(mb_trim($output))->toBe($output);
})->with(addressingFormatterClasses());

it('prints sparse addresses without blank lines', function (string $formatter, array $input): void {
    $output = app($formatter)->format(AddressData::from(
        $input + ['countryCode' => $formatter::countryCode()],
    ));

    expect($output)->not->toBe('')
        ->and($output)->not->toContain("\n\n")
        ->and(mb_trim($output))->toBe($output);
})->with(array_merge(
    array_map(static fn (string $c): array => [$c, ['city' => 'Testville']], array_values(addressingFormatterClasses())),
    array_map(static fn (string $c): array => [$c, ['postcode' => '12345']], array_values(addressingFormatterClasses())),
));
