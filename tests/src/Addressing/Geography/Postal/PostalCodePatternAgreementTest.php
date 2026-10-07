<?php

declare(strict_types=1);

use AIArmada\Addressing\Support\AddressValidationProfiles;
use AIArmada\Addressing\Support\BundledPostalDatasets;

it('accepts every bundled postcode under its country pattern', function (): void {
    $mismatches = [];

    foreach (BundledPostalDatasets::datasets() as [$code, $codesPath]) {
        $pattern = AddressValidationProfiles::forCountry($code)?->pattern;

        if ($pattern === null) {
            $mismatches[] = "{$code}: no pattern for bundled dataset";

            continue;
        }

        $handle = fopen($codesPath, 'r');
        fgetcsv($handle, escape: '\\');

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $postcode = mb_trim((string) ($row[1] ?? ''));

            if ($postcode === '') {
                continue;
            }

            if (! AddressValidationProfiles::matchesPattern($pattern, $postcode)) {
                $mismatches[] = "{$code}: [{$postcode}] rejects";
            }
        }

        fclose($handle);
    }

    expect($mismatches)->toBe([]);
});

it('pairs every codes file with a links file', function (): void {
    $dir = __DIR__ . '/../../../../../packages/addressing/resources/geography';
    $orphans = [];

    foreach (glob($dir . '/*-postal-codes.csv') ?: [] as $codesPath) {
        $linksPath = mb_substr($codesPath, 0, -mb_strlen('-postal-codes.csv')) . '-postal-code-areas.csv';

        if (! is_file($linksPath)) {
            $orphans[] = basename($codesPath);
        }
    }

    expect($orphans)->toBe([]);
});
