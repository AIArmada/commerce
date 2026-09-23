<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportPostalCodesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

function postalCsvDatasets(): array
{
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $datasets = [];

    foreach (glob($dir . '/*-postal-codes.csv') ?: [] as $codesPath) {
        $slug = basename($codesPath, '-postal-codes.csv');
        $linksPath = $dir . '/' . $slug . '-postal-code-areas.csv';

        if (! is_file($linksPath)) {
            continue;
        }

        $handle = fopen($codesPath, 'r');
        $header = $handle !== false ? fgetcsv($handle, escape: '\\') : false;
        $first = $handle !== false ? fgetcsv($handle, escape: '\\') : false;

        if ($handle !== false) {
            fclose($handle);
        }

        if ($header === false || $first === false) {
            continue;
        }

        $datasets[$slug] = [
            'countryCode' => mb_strtoupper(mb_trim((string) ($first[0] ?? ''))),
            'codesPath' => $codesPath,
            'linksPath' => $linksPath,
        ];
    }

    return $datasets;
}

it('imports every bundled postcode dataset without failures', function (string $countryCode, string $codesPath, string $linksPath): void {
    $this->seedCountry($countryCode);
    app(SeedCountryGeographiesAction::class)->execute($countryCode);

    $areaSource = AddressArea::query()->where('country_code', $countryCode)->distinct()->pluck('source')->all();

    expect($areaSource)->toHaveCount(1);

    $result = app(ImportPostalCodesAction::class)->execute(
        new CsvPostalCodeSource($countryCode, $codesPath, $linksPath, $areaSource[0])
    );

    expect($result->hasFailures())->toBeFalse();

    $codes = array_filter(array_map(
        static fn (string $line): string => mb_strtoupper(mb_trim((string) (str_getcsv($line)[1] ?? ''))),
        array_slice(file($codesPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), 1)
    ));

    expect(PostalCode::query()->where('country_code', $countryCode)->count())->toBe(count($codes));

    $links = array_filter(array_map(
        static fn (string $line): array => str_getcsv($line),
        array_slice(file($linksPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), 1)
    ));

    expect(AddressAreaPostalCode::query()->whereHas('postalCode', fn ($q) => $q->where('country_code', $countryCode))->count())
        ->toBe(count($links));

    $primaries = collect();

    AddressAreaPostalCode::query()
        ->where('is_primary', true)
        ->whereHas('postalCode', fn ($q) => $q->where('country_code', $countryCode))
        ->chunkById(500, function ($chunk) use (&$primaries): void {
            $chunk->load('postalCode');

            foreach ($chunk as $link) {
                $primaries->push($link);
            }
        });

    $primaries = $primaries->groupBy(fn ($link) => $link->postalCode->code);

    foreach ($primaries as $code => $rows) {
        expect($rows)->toHaveCount(1, "Postcode {$code} must have exactly one primary link.");
    }

    $linkedCodes = collect();

    AddressAreaPostalCode::query()
        ->whereHas('postalCode', fn ($q) => $q->where('country_code', $countryCode))
        ->chunkById(500, function ($chunk) use (&$linkedCodes): void {
            $chunk->load('postalCode');

            foreach ($chunk as $link) {
                $linkedCodes->push($link->postalCode->code);
            }
        });

    $linkedCodes = $linkedCodes->unique();

    foreach ($linkedCodes as $code) {
        expect($primaries->has($code))->toBeTrue("Postcode {$code} has links but no primary.");
    }
})->with(array_map(
    static fn (array $d): array => [$d['countryCode'], $d['codesPath'], $d['linksPath']],
    postalCsvDatasets()
));
