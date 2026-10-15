<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\CompareAddressingReferenceExportsAction;
use AIArmada\Addressing\Actions\ExportAddressingReferencesAction;
use AIArmada\Addressing\Geography\Malaysia\MalaysiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\AddressingTableResolver;
use AIArmada\Addressing\Support\ReferenceExportContract;
use AIArmada\Addressing\Support\ReferenceExportFile;
use AIArmada\CommerceSupport\Models\Currency;
use AIArmada\CommerceSupport\Models\Timezone;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @return array{country: AddressCountry, state: State, city: City, area: AddressArea, child: AddressArea, postal: PostalCode} */
function seedReferenceExportFixture(string $countryCode = 'MY'): array
{
    $country = AddressCountry::query()->create([
        'iso2' => $countryCode,
        'iso3' => $countryCode . 'X',
        'numeric_code' => $countryCode === 'MY' ? '999' : '998',
        'name' => 'Test ' . $countryCode,
        'phone_code' => '+60',
        'native' => 'Test Native',
        'capital' => 'Test City',
        'region' => 'Test Region',
        'subregion' => 'Test Subregion',
        'tld' => '.xx',
        'latitude' => 3.1234567,
        'longitude' => 101.1234567,
        'emoji' => '🏁',
        'emojiU' => 'U+1F3C1',
        'translations' => ['en' => 'Test country', 'ms' => 'Negara ujian'],
    ]);

    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'name' => 'Example State',
        'country_code' => $countryCode,
        'code' => 'EX',
        'latitude' => 3.1234567,
        'longitude' => 101.1234567,
    ]);

    $city = City::query()->create([
        'country_id' => $country->getKey(),
        'state_id' => $state->getKey(),
        'name' => 'Example City',
        'country_code' => $countryCode,
        'state_code' => 'EX',
        'latitude' => 3.1234567,
        'longitude' => 101.1234567,
    ]);

    $area = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => $countryCode,
        'type' => 'district',
        'level' => 1,
        'name' => 'Example Area',
        'native_name' => 'Area Native',
        'code' => 'A1',
        'slug' => 'custom slug must not export',
        'is_active' => true,
        'latitude' => 3.1234567,
        'longitude' => 101.1234567,
        'source' => 'fixture-provider',
        'source_id' => 'area-' . $countryCode . '-1',
        'source_payload' => ['must_not_export' => 'source payload secret'],
        'metadata' => ['must_not_export' => 'arbitrary metadata secret'],
    ]);

    $child = AddressArea::query()->create([
        'country_id' => $country->getKey(),
        'parent_id' => $area->getKey(),
        'country_code' => $countryCode,
        'type' => 'locality',
        'level' => 2,
        'name' => 'Example Child',
        'code' => 'A2',
        'slug' => 'child-slug',
        'is_active' => true,
        'source' => 'fixture-provider',
        'source_id' => 'area-' . $countryCode . '-2',
    ]);

    AddressAreaName::query()->create([
        'address_area_id' => $area->getKey(),
        'name' => 'Example Alias',
        'source' => 'fixture-provider',
        'name_type' => 'common',
        'is_preferred' => true,
    ]);

    AddressAreaRole::query()->create([
        'address_area_id' => $area->getKey(),
        'role' => 'district',
        'source' => 'fixture-provider',
        'country_code' => $countryCode,
        'is_primary' => true,
    ]);

    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $area->getKey(),
        'child_address_area_id' => $child->getKey(),
        'relationship_type' => 'contains',
        'hierarchy_type' => 'administrative',
        'source' => 'fixture-provider',
        'valid_from' => '2020-01-01',
        'valid_until' => null,
        'metadata' => ['must_not_export' => 'relationship metadata secret'],
    ]);

    AddressAreaStateLink::query()->create([
        'address_area_id' => $area->getKey(),
        'state_id' => $state->getKey(),
        'hierarchy_type' => 'administrative',
        'metadata' => ['must_not_export' => 'link metadata secret'],
    ]);

    $postal = PostalCode::query()->create([
        'country_code' => $countryCode,
        'code' => '40000',
        'is_active' => true,
        'metadata' => ['must_not_export' => 'postcode metadata secret'],
    ]);
    $area->postalCodes()->attach($postal->getKey(), [
        'source' => 'fixture-provider',
        'source_id' => 'coverage-1',
        'relationship_type' => 'served_by',
        'is_primary' => true,
    ]);

    $currency = Currency::query()->create(['code' => $countryCode . 'T', 'name' => 'Test Currency']);
    $timezone = Timezone::query()->create(['name' => $countryCode . '/Timezone']);
    $country->currencies()->attach($currency->getKey(), ['id' => (string) Str::uuid()]);
    $country->timezones()->attach($timezone->getKey(), ['id' => (string) Str::uuid()]);

    return compact('country', 'state', 'city', 'area', 'child', 'postal');
}

function referenceExportPath(): string
{
    return tempnam(sys_get_temp_dir(), 'address-reference-export-test-') ?: throw new RuntimeException('Could not create test export path.');
}

function useOneReferenceProviderScope(): void
{
    config()->set('addressing.geography.providers', [MalaysiaGeographyProvider::class]);
}

final class ReferenceExportAlternateConnectionState extends State
{
    protected $connection = 'reference_export_alternate';
}

final class ReferenceExportGuardPostgresConnection extends Connection
{
    public function getDriverName(): string
    {
        return 'pgsql';
    }

    public function transactionLevel(): int
    {
        return 1;
    }
}

/** @param list<array<string, mixed>> $records */
function writeReferenceExportWithRecords(string $path, array $header, array $records): void
{
    $counts = array_fill_keys(ReferenceExportContract::TYPES, 0);
    $digest = hash_init('sha256');
    $lines = [ReferenceExportContract::encode($header)];

    foreach ($records as $record) {
        $line = ReferenceExportContract::encode($record);
        $lines[] = $line;
        $counts[$record['type']]++;
        hash_update($digest, $line . "\n");
    }

    $lines[] = ReferenceExportContract::encode(['kind' => 'footer', 'counts' => $counts, 'records_sha256' => hash_final($digest)]);
    file_put_contents($path, implode("\n", $lines) . "\n");
    chmod($path, 0600);
}

it('exports all twelve global reference types without instance or arbitrary payload data', function (): void {
    useOneReferenceProviderScope();
    seedReferenceExportFixture();
    $path = referenceExportPath();

    try {
        $result = app(ExportAddressingReferencesAction::class)->execute($path);
        $header = app(ReferenceExportFile::class)->header($path);
        $records = iterator_to_array(app(ReferenceExportFile::class)->records($path));
        $json = file_get_contents($path);

        expect($result['counts'])->toHaveKeys([
            'countries', 'states', 'cities', 'areas', 'area_names', 'area_roles',
            'area_relationships', 'area_state_links', 'postal_codes', 'area_postal_codes',
            'country_currency_links', 'country_timezone_links',
        ])
            ->and(array_filter($result['counts'], static fn (int $count): bool => $count > 0))->toHaveCount(12)
            ->and($header['seed_revision'])->toBeNull()
            ->and($header['scope']['configured_providers'])->toBe([['country' => 'MY', 'provider' => app(MalaysiaGeographyProvider::class)->providerKey()]])
            ->and(collect($records)->firstWhere('type', 'countries')['data']['translations'])->toBe(['en' => 'Test country', 'ms' => 'Negara ujian'])
            ->and(array_unique(array_column($records, 'kind')))->toBe(['record'])
            ->and($json)->not->toContain('custom slug must not export')
            ->and($json)->not->toContain('source payload secret')
            ->and($json)->not->toContain('arbitrary metadata secret')
            ->and($json)->not->toContain('postcode metadata secret')
            ->and($json)->not->toContain('addresses')
            ->and(fileperms($path) & 0777)->toBe(0600);
    } finally {
        @unlink($path);
    }
});

it('filters the export to requested country codes and marks out of scope differences as unknown', function (): void {
    useOneReferenceProviderScope();
    seedReferenceExportFixture('MY');
    seedReferenceExportFixture('SG');
    $malaysia = referenceExportPath();
    $singapore = referenceExportPath();

    try {
        app(ExportAddressingReferencesAction::class)->execute($malaysia, ['MY']);
        app(ExportAddressingReferencesAction::class)->execute($singapore, ['SG']);

        expect(app(ReferenceExportFile::class)->header($malaysia)['scope']['countries'])->toBe(['MY']);

        $comparison = app(CompareAddressingReferenceExportsAction::class)->execute($malaysia, $singapore);

        expect(array_sum($comparison['counts']['scope_unknown']))->toBeGreaterThan(0)
            ->and(array_sum($comparison['counts']['missing']))->toBe(0)
            ->and(array_sum($comparison['counts']['added']))->toBe(0);
    } finally {
        @unlink($malaysia);
        @unlink($singapore);
    }
});

it('exports and compares default-hierarchy state links with an empty hierarchy marker', function (): void {
    useOneReferenceProviderScope();
    $fixture = seedReferenceExportFixture();
    AddressAreaStateLink::query()->create([
        'address_area_id' => $fixture['child']->getKey(),
        'state_id' => $fixture['state']->getKey(),
        'hierarchy_type' => '',
    ]);
    $before = referenceExportPath();
    $after = referenceExportPath();

    try {
        app(ExportAddressingReferencesAction::class)->execute($before, ['MY']);
        app(ExportAddressingReferencesAction::class)->execute($after, ['MY']);

        $comparison = app(CompareAddressingReferenceExportsAction::class)->execute($before, $after);

        expect($comparison['counts']['unchanged']['area_state_links'])->toBe(2);
    } finally {
        @unlink($before);
        @unlink($after);
    }
});

it('reports changed and added records and rejects tampered rows', function (): void {
    useOneReferenceProviderScope();
    $fixture = seedReferenceExportFixture();
    $before = referenceExportPath();
    $after = referenceExportPath();
    $tampered = referenceExportPath();

    try {
        app(ExportAddressingReferencesAction::class)->execute($before, ['MY']);
        $fixture['country']->update(['name' => 'Changed country name']);
        PostalCode::query()->create(['country_code' => 'MY', 'code' => '50000', 'is_active' => true]);
        app(ExportAddressingReferencesAction::class)->execute($after, ['MY']);

        $comparison = app(CompareAddressingReferenceExportsAction::class)->execute($before, $after);
        expect($comparison['counts']['changed']['countries'])->toBe(1)
            ->and($comparison['counts']['added']['postal_codes'])->toBe(1);

        $contents = file_get_contents($after);
        file_put_contents($tampered, preg_replace('/Changed country name/', 'Tampered country name', (string) $contents, 1));

        expect(fn () => app(CompareAddressingReferenceExportsAction::class)->execute($before, $tampered))
            ->toThrow(InvalidArgumentException::class);
    } finally {
        @unlink($before);
        @unlink($after);
        @unlink($tampered);
    }
});

it('reports duplicate natural identities as ambiguous without selecting a winner', function (): void {
    useOneReferenceProviderScope();
    seedReferenceExportFixture();
    $before = referenceExportPath();
    $after = referenceExportPath();

    try {
        app(ExportAddressingReferencesAction::class)->execute($before, ['MY']);
        $reader = app(ReferenceExportFile::class);
        $records = iterator_to_array($reader->records($before));
        $duplicateType = $records[0]['type'];
        $records[] = $records[0];
        usort($records, static fn (array $left, array $right): int => strcmp(
            ReferenceExportContract::sortKey($left['type'], $left['key']),
            ReferenceExportContract::sortKey($right['type'], $right['key']),
        ));
        writeReferenceExportWithRecords($after, $reader->header($before), $records);

        $comparison = app(CompareAddressingReferenceExportsAction::class)->execute($before, $after);

        expect($comparison['counts']['ambiguous'][$duplicateType])->toBe(1)
            ->and(array_sum($comparison['counts']['changed']))->toBe(0)
            ->and($comparison['examples'][0]['status'])->toBe('ambiguous');
    } finally {
        @unlink($before);
        @unlink($after);
    }
});

it('preserves the previous complete output when export scope validation fails', function (): void {
    $path = referenceExportPath();
    file_put_contents($path, 'previous complete export');

    try {
        expect(fn () => app(ExportAddressingReferencesAction::class)->execute($path, ['ZZ']))
            ->toThrow(InvalidArgumentException::class);

        expect(file_get_contents($path))->toBe('previous complete export');
    } finally {
        @unlink($path);
    }
});

it('validates every resolved reference model connection before creating export files', function (): void {
    $defaultConnection = config('database.default');
    config()->set('database.connections.reference_export_alternate', config("database.connections.{$defaultConnection}"));
    config()->set('addressing.models.state', ReferenceExportAlternateConnectionState::class);
    $directory = sys_get_temp_dir() . '/addressing-reference-connection-' . Str::uuid();
    mkdir($directory, 0700);
    $output = $directory . '/references.ndjson';

    try {
        expect(fn () => app(ExportAddressingReferencesAction::class)->execute($output))
            ->toThrow(InvalidArgumentException::class, 'must share the country database connection');
        expect(glob($directory . '/.addressing-reference-export-*'))->toBe([])
            ->and(file_exists($output))->toBeFalse();
    } finally {
        @unlink($output);
        @rmdir($directory);
    }
});

it('rejects an outer PostgreSQL transaction before creating temporary output', function (): void {
    $originalDefault = config('database.default');
    $manager = app('db');
    $manager->extend('reference_export_pg_guard', static fn (array $config, string $name): Connection => new ReferenceExportGuardPostgresConnection(
        null,
        (string) ($config['database'] ?? ''),
        (string) ($config['prefix'] ?? ''),
        $config,
    ));
    config()->set('database.connections.reference_export_pg_guard', ['driver' => 'reference_export_pg_guard', 'database' => 'references']);
    $manager->setDefaultConnection('reference_export_pg_guard');
    $path = referenceExportPath();
    file_put_contents($path, 'existing snapshot');

    try {
        expect(fn () => app(ExportAddressingReferencesAction::class)->execute($path))
            ->toThrow(InvalidArgumentException::class, 'cannot run inside an existing transaction');
        expect(file_get_contents($path))->toBe('existing snapshot')
            ->and(glob(dirname($path) . '/.addressing-reference-export-*'))->toBe([]);
    } finally {
        $manager->purge('reference_export_pg_guard');
        $manager->setDefaultConnection($originalDefault);
        @unlink($path);
    }
});

it('fails on dangling reference rows rather than exporting them as absent relationships', function (): void {
    useOneReferenceProviderScope();
    seedReferenceExportFixture();
    AddressAreaName::query()->create([
        'address_area_id' => (string) Str::uuid(),
        'name' => 'Orphan alias',
        'source' => 'broken-fixture',
        'name_type' => 'common',
        'is_preferred' => false,
    ]);
    $path = referenceExportPath();

    try {
        expect(fn () => app(ExportAddressingReferencesAction::class)->execute($path, ['MY']))
            ->toThrow(InvalidArgumentException::class, 'Dangling area name reference');
        expect(file_get_contents($path))->toBe('');
    } finally {
        @unlink($path);
    }
});

it('keyset-exports over one thousand rows and ignores physical postcode UUIDs in digests', function (): void {
    $country = AddressCountry::query()->create(['iso2' => 'MY', 'name' => 'Malaysia']);
    $path = referenceExportPath();
    $secondPath = referenceExportPath();
    $now = now()->toDateTimeString();
    $makeRows = static function () use ($now): array {
        $rows = [];

        for ($index = 0; $index < 1_001; $index++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'country_code' => 'MY',
                'code' => sprintf('%05d', $index),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    };

    try {
        DB::table(AddressingTableResolver::resolve('postal_codes'))->insert($makeRows());
        $first = app(ExportAddressingReferencesAction::class)->execute($path, ['MY']);
        DB::table(AddressingTableResolver::resolve('postal_codes'))->delete();
        DB::table(AddressingTableResolver::resolve('postal_codes'))->insert($makeRows());
        $second = app(ExportAddressingReferencesAction::class)->execute($secondPath, ['MY']);

        expect($first['counts']['postal_codes'])->toBe(1_001)
            ->and($second['counts']['postal_codes'])->toBe(1_001)
            ->and($first['sha256'])->toBe($second['sha256'])
            ->and(app(CompareAddressingReferenceExportsAction::class)->execute($path, $secondPath)['counts']['unchanged']['postal_codes'])->toBe(1_001);
    } finally {
        DB::table(AddressingTableResolver::resolve('postal_codes'))->delete();
        @unlink($path);
        @unlink($secondPath);
    }
});

it('registers reference export command success and failure paths', function (): void {
    useOneReferenceProviderScope();
    seedReferenceExportFixture();
    $path = referenceExportPath();

    try {
        $this->artisan('address:reference:export', ['--output' => $path, '--country' => ['MY']])->assertSuccessful();
        expect(file_exists($path))->toBeTrue();
        $this->artisan('address:reference:export')->assertFailed();
        $this->artisan('address:reference:compare', ['before' => $path, 'after' => $path])->assertSuccessful();
        $this->artisan('address:reference:compare', ['before' => $path, 'after' => $path . '.missing'])->assertFailed();
    } finally {
        @unlink($path);
    }
});
