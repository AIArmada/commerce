<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportPostalCodesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Contracts\PostalCodeSource;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Support\CsvPostalCodeSource;
use Illuminate\Support\LazyCollection;

const POSTAL_CSV_IMPORT_SAMPLE_SIZE = 400;
const POSTAL_CSV_VIOLATION_SAMPLE = 5;

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
            mb_strtoupper(mb_trim((string) ($first[0] ?? ''))),
            $codesPath,
            $linksPath,
        ];
    }

    return $datasets;
}

/**
 * Stream data rows from a postcode CSV, skipping the header.
 *
 * @return Generator<int, list<string>> Yields line number => row.
 */
function postalCsvStreamRows(string $path): Generator
{
    $handle = fopen($path, 'r');

    if ($handle === false) {
        throw new RuntimeException("Unable to open postcode CSV: {$path}");
    }

    try {
        $line = 1;

        if (fgetcsv($handle, escape: '\\') === false) {
            return;
        }

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;

            yield $line => $row;
        }
    } finally {
        fclose($handle);
    }
}

/**
 * Restrict a postcode source to a code subset, keeping every link per code.
 *
 * Sampling by code (not by row) preserves per-code completeness, so the
 * exactly-one-primary contract stays meaningful on the sample.
 */
final class PostalCsvCodeSampledSource implements PostalCodeSource
{
    /**
     * @param  array<string, true>  $codes  Upper-cased code set to keep.
     */
    public function __construct(
        private readonly PostalCodeSource $inner,
        private readonly array $codes,
    ) {}

    public function key(): string
    {
        return $this->inner->key();
    }

    /** @return LazyCollection<int, PostalCodeData> */
    public function postalCodes(): LazyCollection
    {
        return $this->inner->postalCodes()->filter(
            fn ($item): bool => isset($this->codes[mb_strtoupper($item->code)])
        );
    }
}

it('ships structurally valid postcode datasets', function (string $countryCode, string $codesPath, string $linksPath): void {
    // --- Codes pass: streamed, no DB. ---
    $codes = [];
    $codeCount = 0;
    $dupeCount = 0;
    $dupeSample = [];
    $mismatchCount = 0;
    $mismatchSample = [];

    foreach (postalCsvStreamRows($codesPath) as $line => $row) {
        $rowCountry = mb_strtoupper(mb_trim((string) ($row[0] ?? '')));
        $code = mb_strtoupper(mb_trim((string) ($row[1] ?? '')));

        if ($code === '') {
            // Blank rows are skipped, mirroring CsvPostalCodeSource.
            continue;
        }

        if ($rowCountry !== $countryCode) {
            $mismatchCount++;

            if (count($mismatchSample) < POSTAL_CSV_VIOLATION_SAMPLE) {
                $mismatchSample[] = "line {$line}: [{$rowCountry}]";
            }
        }

        if (isset($codes[$code])) {
            $dupeCount++;

            if (count($dupeSample) < POSTAL_CSV_VIOLATION_SAMPLE) {
                $dupeSample[] = $code;
            }

            continue;
        }

        $codes[$code] = true;
        $codeCount++;
    }

    expect($codeCount)->toBeGreaterThan(0, 'Codes CSV must not be empty.');
    expect($mismatchCount)->toBe(0, "{$mismatchCount} rows with wrong country: " . implode(', ', $mismatchSample));
    expect($dupeCount)->toBe(0, "{$dupeCount} duplicate codes, e.g. " . implode(', ', $dupeSample));

    // --- Areas set: small file, loaded once. ---
    $slug = basename($codesPath, '-postal-codes.csv');
    $areasPath = dirname($codesPath) . '/' . $slug . '-address-areas.csv';

    expect($areasPath)->toBeFile("Missing areas file for {$slug}; links cannot resolve.");

    $areas = [];
    $areaCount = 0;

    foreach (postalCsvStreamRows($areasPath) as $row) {
        $sourceId = mb_trim((string) ($row[0] ?? ''));

        if ($sourceId === '') {
            continue;
        }

        $areas[$sourceId] = true;
        $areaCount++;
    }

    expect($areaCount)->toBeGreaterThan(0, "Areas file for {$slug} must not be empty.");

    // --- Links pass: streamed, referential + primary checks. ---
    $unknownCodeCount = 0;
    $unknownCodeSample = [];
    $unknownAreaCount = 0;
    $unknownAreaSample = [];
    $linked = [];
    $primaryCounts = [];

    foreach (postalCsvStreamRows($linksPath) as $row) {
        $code = mb_strtoupper(mb_trim((string) ($row[0] ?? '')));
        $areaSourceId = mb_trim((string) ($row[1] ?? ''));

        if ($code === '' || $areaSourceId === '') {
            // Blank rows are skipped, mirroring CsvPostalCodeSource.
            continue;
        }

        $linked[$code] = true;

        if (! isset($codes[$code])) {
            $unknownCodeCount++;

            if (count($unknownCodeSample) < POSTAL_CSV_VIOLATION_SAMPLE) {
                $unknownCodeSample[] = $code;
            }
        }

        if (! isset($areas[$areaSourceId])) {
            $unknownAreaCount++;

            if (count($unknownAreaSample) < POSTAL_CSV_VIOLATION_SAMPLE) {
                $unknownAreaSample[] = $areaSourceId;
            }
        }

        if (mb_trim((string) ($row[3] ?? '')) === 'true') {
            $primaryCounts[$code] = ($primaryCounts[$code] ?? 0) + 1;
        }
    }

    // A header-only links file is valid: codes-only overlays import the
    // codes with no area links (CsvPostalCodeSource covers this).
    expect($unknownCodeCount)->toBe(0, "{$unknownCodeCount} links to unknown postcodes, e.g. " . implode(', ', $unknownCodeSample));
    expect($unknownAreaCount)->toBe(0, "{$unknownAreaCount} links to unknown areas, e.g. " . implode(', ', $unknownAreaSample));

    $multiCount = 0;
    $multiSample = [];
    $missingCount = 0;
    $missingSample = [];

    foreach ($linked as $code => $_) {
        $primaries = $primaryCounts[$code] ?? 0;

        if ($primaries > 1) {
            $multiCount++;

            if (count($multiSample) < POSTAL_CSV_VIOLATION_SAMPLE) {
                $multiSample[] = "{$code} ({$primaries})";
            }
        } elseif ($primaries === 0) {
            $missingCount++;

            if (count($missingSample) < POSTAL_CSV_VIOLATION_SAMPLE) {
                $missingSample[] = $code;
            }
        }
    }

    expect($multiCount)->toBe(0, "{$multiCount} postcodes with multiple primaries, e.g. " . implode(', ', $multiSample));
    expect($missingCount)->toBe(0, "{$missingCount} linked postcodes without a primary, e.g. " . implode(', ', $missingSample));
})->with(postalCsvDatasets());

it('imports sampled postcodes without failures', function (string $countryCode, string $codesPath, string $linksPath): void {
    $this->seedCountry($countryCode);
    app(SeedCountryGeographiesAction::class)->execute($countryCode);

    $areaSource = AddressArea::query()->where('country_code', $countryCode)->distinct()->pluck('source')->all();

    expect($areaSource)->toHaveCount(1);

    $source = new CsvPostalCodeSource($countryCode, $codesPath, $linksPath, $areaSource[0]);

    // Deterministic stride over the codes file so the DB import stays
    // bounded while still covering head, tail, and unlinked codes.
    $ordered = [];

    foreach (postalCsvStreamRows($codesPath) as $row) {
        $code = mb_strtoupper(mb_trim((string) ($row[1] ?? '')));

        if ($code !== '' && ! isset($ordered[$code])) {
            $ordered[$code] = true;
        }
    }

    $ordered = array_keys($ordered);

    expect($ordered)->not->toBeEmpty();

    $stride = max(1, (int) floor(count($ordered) / POSTAL_CSV_IMPORT_SAMPLE_SIZE));
    $sample = [];

    foreach ($ordered as $index => $code) {
        if ($index % $stride === 0) {
            $sample[$code] = true;
        }
    }

    $result = app(ImportPostalCodesAction::class)->execute(new PostalCsvCodeSampledSource($source, $sample));

    $failureSample = array_slice(array_map(
        static fn ($failure): string => "[{$failure->code}] {$failure->reason}",
        $result->failures,
    ), 0, POSTAL_CSV_VIOLATION_SAMPLE);

    expect($result->hasFailures())->toBeFalse(
        count($result->failures) . ' import failures, e.g. ' . implode('; ', $failureSample)
    );

    $expectedLinks = 0;
    $expectedPrimaries = [];
    $expectedLinked = [];

    foreach ((new PostalCsvCodeSampledSource($source, $sample))->postalCodes() as $item) {
        if ($item->areaSourceId === null) {
            continue;
        }

        $expectedLinks++;
        $expectedLinked[mb_strtoupper($item->code)] = true;

        if ($item->isPrimary) {
            $expectedPrimaries[mb_strtoupper($item->code)] = true;
        }
    }

    expect(PostalCode::query()->where('country_code', $countryCode)->count())->toBe(count($sample));
    expect(AddressAreaPostalCode::query()->whereHas('postalCode', fn ($q) => $q->where('country_code', $countryCode))->count())
        ->toBe($expectedLinks);

    $primaryCounts = AddressAreaPostalCode::query()
        ->where('is_primary', true)
        ->whereHas('postalCode', fn ($q) => $q->where('country_code', $countryCode))
        ->selectRaw('postal_code_id, COUNT(*) AS aggregate')
        ->groupBy('postal_code_id')
        ->pluck('aggregate', 'postal_code_id')
        ->all();

    $multi = array_keys(array_filter($primaryCounts, static fn ($count): bool => (int) $count !== 1));

    expect($multi)->toBe([], count($multi) . ' sampled postcodes with != 1 primary link.');

    $missing = array_keys(array_diff_key($expectedLinked, $expectedPrimaries));

    expect($missing)->toBe([], count($missing) . ' sampled postcodes without a primary, e.g. ' . implode(', ', array_slice($missing, 0, POSTAL_CSV_VIOLATION_SAMPLE)));
})->with(postalCsvDatasets());
