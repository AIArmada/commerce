<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Addressing\Geography\Postal;

use AIArmada\Addressing\Actions\ImportPostalCodesAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Support\CsvPostalCodeSource;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Shared postcode-dataset helpers for the PostalCodeCsvImport shards.
 *
 * The 160 bundled postcode datasets are sharded across files (see
 * PostalCodeCsvImportShard*Test) so parallel runs split the work, the
 * same way GeographyProviderSeedShard*Test shards full seeding.
 *
 * The import test seeds stub areas for exactly the areas its sampled
 * codes link to — never a full country. Full-tree seeding is already
 * covered by the seed shards, and the import only resolves areas by
 * (country_code, source, source_id), so stubs exercise the same path
 * without importing ~113k areas per shard file.
 */
final class PostalCsvSamples
{
    public const int IMPORT_SAMPLE_SIZE = 400;

    public const int VIOLATION_SAMPLE = 5;

    public const int SHARD_TOTAL = 4;

    /**
     * @return array<string, array{string, string, string}> Slug => [countryCode, codesPath, linksPath].
     */
    public static function datasets(): array
    {
        $dir = __DIR__ . '/../../../../../packages/addressing/resources/geography';
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

        ksort($datasets);

        return $datasets;
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function datasetsForShard(int $shard): array
    {
        $filtered = [];
        $index = 0;

        foreach (self::datasets() as $slug => $dataset) {
            if ($index++ % self::SHARD_TOTAL === $shard) {
                $filtered[$slug] = $dataset;
            }
        }

        return $filtered;
    }

    /**
     * Stream data rows from a postcode CSV, skipping the header.
     *
     * @return Generator<int, list<string>> Yields line number => row.
     */
    public static function streamRows(string $path): Generator
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

    public static function assertStructurallyValid(string $countryCode, string $codesPath, string $linksPath): void
    {
        // --- Codes pass: streamed, no DB. ---
        $codes = [];
        $codeCount = 0;
        $dupeCount = 0;
        $dupeSample = [];
        $mismatchCount = 0;
        $mismatchSample = [];

        foreach (self::streamRows($codesPath) as $line => $row) {
            $rowCountry = mb_strtoupper(mb_trim((string) ($row[0] ?? '')));
            $code = mb_strtoupper(mb_trim((string) ($row[1] ?? '')));

            if ($code === '') {
                // Blank rows are skipped, mirroring CsvPostalCodeSource.
                continue;
            }

            if ($rowCountry !== $countryCode) {
                $mismatchCount++;

                if (count($mismatchSample) < self::VIOLATION_SAMPLE) {
                    $mismatchSample[] = "line {$line}: [{$rowCountry}]";
                }
            }

            if (isset($codes[$code])) {
                $dupeCount++;

                if (count($dupeSample) < self::VIOLATION_SAMPLE) {
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

        foreach (self::streamRows($areasPath) as $row) {
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

        foreach (self::streamRows($linksPath) as $row) {
            $code = mb_strtoupper(mb_trim((string) ($row[0] ?? '')));
            $areaSourceId = mb_trim((string) ($row[1] ?? ''));

            if ($code === '' || $areaSourceId === '') {
                // Blank rows are skipped, mirroring CsvPostalCodeSource.
                continue;
            }

            $linked[$code] = true;

            if (! isset($codes[$code])) {
                $unknownCodeCount++;

                if (count($unknownCodeSample) < self::VIOLATION_SAMPLE) {
                    $unknownCodeSample[] = $code;
                }
            }

            if (! isset($areas[$areaSourceId])) {
                $unknownAreaCount++;

                if (count($unknownAreaSample) < self::VIOLATION_SAMPLE) {
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

                if (count($multiSample) < self::VIOLATION_SAMPLE) {
                    $multiSample[] = "{$code} ({$primaries})";
                }
            } elseif ($primaries === 0) {
                $missingCount++;

                if (count($missingSample) < self::VIOLATION_SAMPLE) {
                    $missingSample[] = $code;
                }
            }
        }

        expect($multiCount)->toBe(0, "{$multiCount} postcodes with multiple primaries, e.g. " . implode(', ', $multiSample));
        expect($missingCount)->toBe(0, "{$missingCount} linked postcodes without a primary, e.g. " . implode(', ', $missingSample));
    }

    public static function importSample(AddressCountry $country, string $countryCode, string $codesPath, string $linksPath): void
    {
        // Deterministic stride over the codes file so the DB import stays
        // bounded while still covering head, tail, and unlinked codes.
        $ordered = [];

        foreach (self::streamRows($codesPath) as $row) {
            $code = mb_strtoupper(mb_trim((string) ($row[1] ?? '')));

            if ($code !== '' && ! isset($ordered[$code])) {
                $ordered[$code] = true;
            }
        }

        $ordered = array_keys($ordered);

        expect($ordered)->not->toBeEmpty();

        $stride = max(1, (int) floor(count($ordered) / self::IMPORT_SAMPLE_SIZE));
        $sample = [];

        foreach ($ordered as $index => $code) {
            if ($index % $stride === 0) {
                $sample[$code] = true;
            }
        }

        $stubSource = self::seedStubAreas($country, $countryCode, $codesPath, $linksPath, $sample);
        $source = new CsvPostalCodeSource($countryCode, $codesPath, $linksPath, $stubSource);

        $result = app(ImportPostalCodesAction::class)->execute(new PostalCsvCodeSampledSource($source, $sample));

        $failureSample = array_slice(array_map(
            static fn ($failure): string => "[{$failure->code}] {$failure->reason}",
            $result->failures,
        ), 0, self::VIOLATION_SAMPLE);

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

        expect($missing)->toBe([], count($missing) . ' sampled postcodes without a primary, e.g. ' . implode(', ', array_slice($missing, 0, self::VIOLATION_SAMPLE)));
    }

    /**
     * Insert stub areas for exactly the areas the sampled codes link to.
     *
     * The import resolves areas by (country_code, source, source_id) only,
     * so real CSV values under a test source exercise the same path as a
     * full seeding at a fraction of the cost. Returns the stub source key.
     *
     * @param  array<string, true>  $sample  Upper-cased sampled code set.
     */
    private static function seedStubAreas(AddressCountry $country, string $countryCode, string $codesPath, string $linksPath, array $sample): string
    {
        $slug = basename($codesPath, '-postal-codes.csv');
        $stubSource = 'test-postal-' . $slug;

        $wanted = [];

        foreach (self::streamRows($linksPath) as $row) {
            $code = mb_strtoupper(mb_trim((string) ($row[0] ?? '')));
            $areaSourceId = mb_trim((string) ($row[1] ?? ''));

            if ($code === '' || $areaSourceId === '' || ! isset($sample[$code])) {
                continue;
            }

            $wanted[$areaSourceId] = true;
        }

        if ($wanted === []) {
            return $stubSource;
        }

        $areasPath = dirname($codesPath) . '/' . $slug . '-address-areas.csv';
        $now = Carbon::now()->toDateTimeString();
        $stubs = [];

        foreach (self::streamRows($areasPath) as $row) {
            $sourceId = mb_trim((string) ($row[0] ?? ''));

            if ($sourceId === '' || ! isset($wanted[$sourceId])) {
                continue;
            }

            $name = mb_trim((string) ($row[3] ?? ''));
            $level = mb_trim((string) ($row[7] ?? ''));

            $stubs[] = [
                'id' => (string) Str::uuid(),
                'country_id' => $country->getKey(),
                'country_code' => $countryCode,
                'type' => mb_trim((string) ($row[2] ?? '')) ?: 'locality',
                'level' => is_numeric($level) ? (int) $level : null,
                'name' => $name === '' ? $sourceId : $name,
                'code' => mb_trim((string) ($row[5] ?? '')) ?: null,
                'slug' => $sourceId,
                'source' => $stubSource,
                'source_id' => $sourceId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            unset($wanted[$sourceId]);

            if ($wanted === []) {
                break;
            }
        }

        // The structural test proves every link resolves; a leftover here
        // means the fixture is broken, not the import.
        expect($wanted)->toBe([], 'Sampled links reference areas missing from the areas file, e.g. ' . implode(', ', array_slice(array_keys($wanted), 0, self::VIOLATION_SAMPLE)));

        AddressArea::query()->insert($stubs);

        return $stubSource;
    }
}
