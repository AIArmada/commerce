<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ExportAddressingReferencesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use AIArmada\Addressing\Support\ReferenceExportFile;
use AIArmada\Commerce\Tests\Addressing\AddressingDatabaseTestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class PostgresReseedingGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public static string $areaName = 'PostgreSQL Area';

    public function providerKey(): string
    {
        return 'test.postgres-reseeding';
    }

    public function countryCode(): string
    {
        return 'BL';
    }

    public function seed(AddressCountry $country): void {}

    public function addressHierarchies(): array
    {
        return [];
    }

    public function addressAreaSource(): AddressAreaSource
    {
        return new ArrayAddressAreaSource('test_postgres_reseeding', [
            new AddressAreaData(
                source: 'test_postgres_reseeding',
                sourceId: 'postgres-area',
                countryCode: 'BL',
                type: 'locality',
                name: self::$areaName,
                level: 1,
                latitude: 3.1415927,
                longitude: 2.7182818,
                metadata: [
                    'labels' => ['z' => true, 'a' => 'Café'],
                    'verified' => true,
                ],
            ),
        ]);
    }

    public function stateAreaMappings(): array
    {
        return [];
    }

    public function areaRoles(AddressCountry $country): array
    {
        return [];
    }

    public function areaNames(AddressCountry $country): array
    {
        return [];
    }

    public function areaRelationships(AddressCountry $country): array
    {
        return [];
    }
}

class PostgresAddressingDatabaseTestCase extends AddressingDatabaseTestCase
{
    private static ?string $schema = null;

    private static bool $cleanupRegistered = false;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $host = getenv('ADDRESSING_RESEED_PG_HOST');
        $database = getenv('ADDRESSING_RESEED_PG_DATABASE');

        if ($host === false || $database === false) {
            return;
        }

        $port = getenv('ADDRESSING_RESEED_PG_PORT') ?: '5432';
        $username = getenv('ADDRESSING_RESEED_PG_USERNAME') ?: '';
        $password = getenv('ADDRESSING_RESEED_PG_PASSWORD') ?: '';

        if (self::$schema === null) {
            $worker = getenv('TEST_TOKEN') ?: (string) getmypid();
            $worker = mb_substr(preg_replace('/[^A-Za-z0-9_]/', '_', $worker) ?: 'local', 0, 16);
            self::$schema = 'addressing_reseed_' . $worker . '_' . bin2hex(random_bytes(4));

            $pdo = self::newPostgresConnection($host, $port, $database, $username, $password);
            $pdo->exec('CREATE SCHEMA "' . self::$schema . '"');

            if (! self::$cleanupRegistered) {
                self::$cleanupRegistered = true;
                $schema = self::$schema;

                register_shutdown_function(static function () use ($host, $port, $database, $username, $password, $schema): void {
                    $pdo = self::newPostgresConnection($host, $port, $database, $username, $password);
                    $pdo->exec('DROP SCHEMA IF EXISTS "' . $schema . '" CASCADE');
                });
            }
        }

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'pgsql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => self::$schema,
            'sslmode' => getenv('ADDRESSING_RESEED_PG_SSLMODE') ?: 'prefer',
        ]);
    }

    public static function newPostgresConnection(string $host, string $port, string $database, string $username, string $password): PDO
    {
        $pdo = new PDO(
            'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $database,
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        return $pdo;
    }

    public static function schema(): string
    {
        return self::$schema ?? throw new RuntimeException('The PostgreSQL schema has not been initialized.');
    }
}

uses(PostgresAddressingDatabaseTestCase::class);

beforeEach(function (): void {
    if (getenv('ADDRESSING_RESEED_PG_HOST') === false || getenv('ADDRESSING_RESEED_PG_DATABASE') === false) {
        $this->markTestSkipped('Set ADDRESSING_RESEED_PG_HOST and ADDRESSING_RESEED_PG_DATABASE to run the PostgreSQL reseeding smoke test.');
    }

    PostgresReseedingGeographyProvider::$areaName = 'PostgreSQL Area';
});

it('keeps decimal geography and JSON stable on PostgreSQL no-op reseeds', function (): void {
    config(['addressing.geography.providers' => [PostgresReseedingGeographyProvider::class]]);
    $this->seedCountry('BL');

    $seeder = app(SeedCountryGeographiesAction::class);
    $seeder->execute('BL');

    $area = AddressArea::query()
        ->where('source', 'test_postgres_reseeding')
        ->where('source_id', 'postgres-area')
        ->firstOrFail();

    $table = $area->getTable();

    $columns = DB::select(
        'select column_name, data_type, numeric_precision, numeric_scale from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name in (?, ?, ?)',
        [$table, 'latitude', 'longitude', 'metadata'],
    );
    $columnTypes = collect($columns)->keyBy('column_name');
    $before = DB::table($table)->where('id', $area->getKey())->first();
    $metadata = json_decode((string) $before->metadata, true, 512, JSON_THROW_ON_ERROR);

    $this->travel(1)->minutes();
    $result = $seeder->execute('BL');

    $area->refresh();
    $after = DB::table($table)->where('id', $area->getKey())->first();

    expect($columnTypes->get('latitude')?->data_type)->toBe('numeric')
        ->and((int) $columnTypes->get('latitude')?->numeric_precision)->toBe(10)
        ->and((int) $columnTypes->get('latitude')?->numeric_scale)->toBe(7)
        ->and($columnTypes->get('metadata')?->data_type)->toBeIn(['json', 'jsonb'])
        ->and((string) $before->latitude)->toBe('3.1415927')
        ->and((string) $before->longitude)->toBe('2.7182818')
        ->and($metadata['labels'])->toBe(['a' => 'Café', 'z' => true])
        ->and($metadata['verified'])->toBeTrue()
        ->and($after)->toEqual($before)
        ->and($result['areas']['BL'])->toBe(['created' => 0, 'updated' => 0, 'skipped' => 1]);
});

it('holds the PostgreSQL area row lock until its source update commits', function (): void {
    config(['addressing.geography.providers' => [PostgresReseedingGeographyProvider::class]]);
    $this->seedCountry('BL');

    $seeder = app(SeedCountryGeographiesAction::class);
    $seeder->execute('BL');

    $area = AddressArea::query()
        ->where('source', 'test_postgres_reseeding')
        ->where('source_id', 'postgres-area')
        ->firstOrFail();

    DB::connection('testing')->commit();
    DB::connection('testing')->beginTransaction();

    $host = (string) getenv('ADDRESSING_RESEED_PG_HOST');
    $port = getenv('ADDRESSING_RESEED_PG_PORT') ?: '5432';
    $database = (string) getenv('ADDRESSING_RESEED_PG_DATABASE');
    $username = getenv('ADDRESSING_RESEED_PG_USERNAME') ?: '';
    $password = getenv('ADDRESSING_RESEED_PG_PASSWORD') ?: '';
    $pdo = PostgresAddressingDatabaseTestCase::newPostgresConnection($host, $port, $database, $username, $password);
    $pdo->exec('SET search_path TO "' . PostgresAddressingDatabaseTestCase::schema() . '"');
    $pdo->exec("SET lock_timeout = '100ms'");
    $secondConnection = [
        'schema' => $pdo->query('SELECT current_schema()')->fetchColumn(),
        'pid' => $pdo->query('SELECT pg_backend_pid()')->fetchColumn(),
    ];
    $visibleRowQuery = $pdo->prepare('SELECT COUNT(*) FROM "' . $area->getTable() . '" WHERE "id" = ?');
    $visibleRowQuery->execute([$area->getKey()]);
    $visibleRows = (int) $visibleRowQuery->fetchColumn();

    $probeFired = false;
    $lockRejected = false;
    $externalAffectedRows = null;
    $mainTransactionLevel = null;
    $mainBackendId = null;
    $areaLockQuerySeen = false;
    $table = $area->getTable();

    DB::listen(function (QueryExecuted $query) use (&$areaLockQuerySeen, $table): void {
        if (str_contains(mb_strtolower($query->sql), mb_strtolower($table))
            && str_contains(mb_strtolower($query->sql), 'for update')) {
            $areaLockQuerySeen = true;
        }
    });

    Event::listen('eloquent.saving: ' . AddressArea::class, function (AddressArea $savingArea) use ($pdo, $table, $area, &$probeFired, &$lockRejected, &$externalAffectedRows, &$mainTransactionLevel, &$mainBackendId): void {
        if ($probeFired || ! $savingArea->exists || $savingArea->getKey() !== $area->getKey()) {
            return;
        }

        $probeFired = true;
        $mainTransactionLevel = DB::transactionLevel();
        $mainBackendId = DB::selectOne('SELECT pg_backend_pid() as pid')->pid;

        try {
            $statement = $pdo->prepare('UPDATE "' . $table . '" SET "name" = ? WHERE "id" = ?');
            $statement->execute(['Concurrent edit', $savingArea->getKey()]);
            $externalAffectedRows = $statement->rowCount();
        } catch (PDOException $exception) {
            $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();

            if ($sqlState !== '55P03') {
                throw $exception;
            }

            $lockRejected = true;
        }
    });

    PostgresReseedingGeographyProvider::$areaName = 'Canonical update';
    $seeder->execute('BL');

    $diagnostic = json_encode([
        'area_lock_query_seen' => $areaLockQuerySeen,
        'main_transaction_level' => $mainTransactionLevel,
        'main_backend_id' => $mainBackendId,
        'second_connection' => $secondConnection,
        'visible_rows_before_probe' => $visibleRows,
        'external_affected_rows' => $externalAffectedRows,
    ], JSON_THROW_ON_ERROR);

    expect($probeFired)->toBeTrue()
        ->and($visibleRows)->toBe(1)
        ->and($areaLockQuerySeen)->toBeTrue()
        ->and($mainTransactionLevel)->toBeGreaterThan(0)
        ->and((string) $mainBackendId)->not->toBe((string) $secondConnection['pid'])
        ->and($lockRejected)->toBeTrue($diagnostic)
        ->and(AddressArea::query()->findOrFail($area->getKey())->name)->toBe('Canonical update');
});

it('exports the same PostgreSQL reference snapshot as canonical JSON records', function (): void {
    config(['addressing.geography.providers' => [PostgresReseedingGeographyProvider::class]]);
    $this->seedCountry('BL');
    app(SeedCountryGeographiesAction::class)->execute('BL');

    DB::connection('testing')->commit();

    $firstPath = tempnam(sys_get_temp_dir(), 'addressing-reference-export-');
    $secondPath = tempnam(sys_get_temp_dir(), 'addressing-reference-export-');

    if ($firstPath === false || $secondPath === false) {
        throw new RuntimeException('Could not create temporary paths for PostgreSQL reference export tests.');
    }

    try {
        $exporter = app(ExportAddressingReferencesAction::class);
        $first = $exporter->execute($firstPath, ['BL']);
        $second = $exporter->execute($secondPath, ['BL']);
        $firstRecords = iterator_to_array(app(ReferenceExportFile::class)->records($firstPath));
        $secondRecords = iterator_to_array(app(ReferenceExportFile::class)->records($secondPath));
        $areaRecord = collect($firstRecords)->first(
            static fn (array $record): bool => $record['type'] === 'areas'
                && $record['key']['source_id'] === 'postgres-area',
        );

        expect($first['countries'])->toBe(['BL'])
            ->and($first['counts'])->toBe($second['counts'])
            ->and($first['sha256'])->toMatch('/^[a-f0-9]{64}$/')
            ->and($second['sha256'])->toBe($first['sha256'])
            ->and($secondRecords)->toBe($firstRecords)
            ->and($areaRecord['data']['latitude'])->toBe('3.1415927')
            ->and($areaRecord['data']['longitude'])->toBe('2.7182818')
            ->and($areaRecord['data']['is_active'])->toBeTrue();
    } finally {
        if (is_file($firstPath)) {
            unlink($firstPath);
        }

        if (is_file($secondPath)) {
            unlink($secondPath);
        }

        DB::connection('testing')->beginTransaction();
    }
});
