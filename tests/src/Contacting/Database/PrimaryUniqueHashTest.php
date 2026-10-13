<?php

declare(strict_types=1);

use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\Connection;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function f02PdoStub(string $serverVersion): PDO
{
    return new class($serverVersion) extends PDO
    {
        public function __construct(private string $serverVersion)
        {
            // No parent constructor: no network connection is opened.
        }

        public function getAttribute(int $attribute): mixed
        {
            if ($attribute === PDO::ATTR_SERVER_VERSION) {
                return $this->serverVersion;
            }

            return null;
        }
    };
}

function f02LoadMigration(string $path): Migration
{
    static $cache = [];

    if (! isset($cache[$path])) {
        $cache[$path] = require $path;
    }

    return $cache[$path];
}

/**
 * @return list<string>
 */
function f02CompileMigrationDdl(string $migrationPath, string $driver): array
{
    $isMaria = in_array($driver, ['mariadb', 'mysql-mariadb'], true);
    $pdoVersion = $isMaria ? '11.4.2-MariaDB' : '8.0.36';
    $pdo = f02PdoStub($pdoVersion);

    $connection = $driver === 'mariadb'
        ? new MariaDbConnection($pdo, 'f02_test', '', ['driver' => 'mariadb', 'database' => 'f02_test'])
        : new MySqlConnection($pdo, 'f02_test', '', ['driver' => 'mysql', 'database' => 'f02_test']);

    if ($isMaria) {
        expect($connection->isMaria())->toBeTrue();
    } else {
        expect($connection->isMaria())->toBeFalse();
    }

    $manager = app('db');
    $connectionName = 'f02_' . $driver;
    $manager->extend($connectionName, static fn (): Connection => $connection);
    config()->set("database.connections.{$connectionName}", ['driver' => $driver === 'mariadb' ? 'mariadb' : 'mysql', 'database' => 'f02_test']);

    $originalDefault = $manager->getDefaultConnection();
    DB::setDefaultConnection($connectionName);

    try {
        $migration = f02LoadMigration($migrationPath);
        $queries = $connection->pretend(static fn (): mixed => $migration->up());

        return array_map(static fn (array $entry): string => (string) $entry['query'], $queries);
    } finally {
        DB::setDefaultConnection($originalDefault);
        $manager->purge($connectionName);
        $manager->forgetExtension($connectionName);
    }
}

function f02ContactRow(string $contactableType, string $contactableId, string $type, string $purpose, bool $primary = true, ?string $ownerType = null, ?string $ownerId = null): array
{
    return [
        'id' => (string) Str::uuid(),
        'contactable_type' => $contactableType,
        'contactable_id' => $contactableId,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'type' => $type,
        'purpose' => $purpose,
        'value' => 'f02-' . Str::lower(Str::random(8)) . '@example.com',
        'is_primary' => $primary,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

it('accepts long primary tuples that share a 512-char JSON prefix', function (): void {
    $table = (new ContactMethod)->getTable();
    $contactableId = (string) Str::uuid();

    // Max-width tuple (191/36/191/191) so the legacy truncated functional
    // key would see both rows as the same 512-char prefix; the SHA-256
    // length-prefixed hash covers the full tuple, keeping them separate.
    // Direct table writes bypass model guards for raw DB-level coverage.
    $longType = str_repeat('C', 191);
    $headType = str_repeat('t', 191);
    $base = str_repeat('p', 100);
    $purposeA = $base . str_repeat('A', 91);
    $purposeB = $base . str_repeat('B', 91);

    $jsonA = json_encode([$longType, $contactableId, $headType, $purposeA]);
    $jsonB = json_encode([$longType, $contactableId, $headType, $purposeB]);

    expect(mb_strlen((string) $jsonA))->toBeGreaterThan(512)
        ->and($jsonA)->not->toBe($jsonB)
        ->and(mb_substr((string) $jsonA, 0, 512))->toBe(mb_substr((string) $jsonB, 0, 512));

    DB::table($table)->insert(f02ContactRow($longType, $contactableId, $headType, $purposeA));
    DB::table($table)->insert(f02ContactRow($longType, $contactableId, $headType, $purposeB));

    expect(DB::table($table)->where('contactable_id', $contactableId)->where('type', $headType)->count())->toBe(2);
});

it('rejects exact duplicate primary tuples', function (): void {
    $table = (new ContactMethod)->getTable();
    $customer = Customer::create(['first_name' => 'F02', 'last_name' => 'Dup']);

    $row = f02ContactRow($customer->getMorphClass(), (string) $customer->getKey(), 'email', 'general');

    DB::table($table)->insert($row);

    $duplicate = $row;
    $duplicate['id'] = (string) Str::uuid();
    $duplicate['value'] = 'other@example.com';

    expect(fn (): mixed => DB::table($table)->insert($duplicate))->toThrow(QueryException::class);
});

it('scopes primary uniqueness to owner while preserving global uniqueness', function (): void {
    $table = (new ContactMethod)->getTable();
    $customer = Customer::create(['first_name' => 'F02', 'last_name' => 'Owners']);
    $row = f02ContactRow($customer->getMorphClass(), (string) $customer->getKey(), 'email', 'general', true, 'tenant', (string) Str::uuid());

    DB::table($table)->insert($row);
    $otherOwner = $row;
    $otherOwner['id'] = (string) Str::uuid();
    $otherOwner['owner_id'] = (string) Str::uuid();
    DB::table($table)->insert($otherOwner);

    $global = f02ContactRow($customer->getMorphClass(), (string) $customer->getKey(), 'phone', 'general');
    DB::table($table)->insert($global);
    $duplicateGlobal = $global;
    $duplicateGlobal['id'] = (string) Str::uuid();

    expect(fn (): mixed => DB::table($table)->insert($duplicateGlobal))->toThrow(QueryException::class)
        ->and(DB::table($table)->where('contactable_id', $customer->getKey())->where('type', 'email')->count())->toBe(2);
});

it('keeps delimiter-containing tuples distinct under length-prefixed hashing', function (): void {
    $table = (new ContactMethod)->getTable();
    $customer = Customer::create(['first_name' => 'F02', 'last_name' => 'Delim']);

    // A naive "type|purpose" concatenation collides ("a:b|c" shapes);
    // length prefixes ("3:a:b|" vs "1:a|") keep these distinct.
    $packingA = '3:a:b|1:c|';
    $packingB = '1:a|3:b:c|';

    expect($packingA)->not->toBe($packingB);

    DB::table($table)->insert(f02ContactRow($customer->getMorphClass(), (string) $customer->getKey(), 'a:b', 'c'));
    DB::table($table)->insert(f02ContactRow($customer->getMorphClass(), (string) $customer->getKey(), 'a', 'b:c'));

    expect(DB::table($table)->where('contactable_id', $customer->getKey())->count())->toBe(2);
});

it('allows duplicate non-primary and unscoped parent tuples', function (): void {
    $table = (new ContactMethod)->getTable();
    $customer = Customer::create(['first_name' => 'F02', 'last_name' => 'Null']);

    DB::table($table)->insert(f02ContactRow($customer->getMorphClass(), (string) $customer->getKey(), 'email', 'general', false));
    DB::table($table)->insert(f02ContactRow($customer->getMorphClass(), (string) $customer->getKey(), 'email', 'general', false));

    $unscoped = f02ContactRow($customer->getMorphClass(), (string) $customer->getKey(), 'phone', 'general');
    $unscoped['contactable_type'] = null;
    $unscoped['contactable_id'] = null;
    DB::table($table)->insert($unscoped);

    $unscoped2 = $unscoped;
    $unscoped2['id'] = (string) Str::uuid();
    DB::table($table)->insert($unscoped2);

    expect(DB::table($table)->where('contactable_id', $customer->getKey())->where('is_primary', false)->count())->toBe(2)
        ->and(DB::table($table)->whereNull('contactable_type')->whereNull('contactable_id')->count())->toBe(2);
});

it('compiles contact_methods MySQL-family DDL with a virtual SHA-256 hash and ordinary unique index', function (): void {
    // Compiled-DDL verification only: this executes the owning migration
    // under Laravel's real MySQL/MariaDB grammars in pretend mode and
    // asserts on the compiled SQL. It does NOT prove live MySQL/MariaDB
    // execution. The PDO stub opens no database connection.
    $path = __DIR__ . '/../../../../packages/contacting/database/migrations/2000_01_01_000001_create_contact_methods_table.php';

    foreach (['mysql', 'mariadb', 'mysql-mariadb'] as $driver) {
        $ddl = f02CompileMigrationDdl($path, $driver);
        $all = implode("\n", $ddl);
        $lower = mb_strtolower($all);

        expect($all)->toContain('SHA2(')
            ->and($all)->toContain('CONCAT(')
            ->and($all)->toContain('CHAR_LENGTH(')
            ->and($all)->toContain('`contactable_type`')
            ->and($all)->toContain('`contactable_id`')
            ->and($all)->toContain('`type`')
            ->and($all)->toContain('`purpose`')
            ->and($all)->toContain('`is_primary`')
            ->and($all)->toContain('primary_unique_hash');

        expect($lower)->toContain(in_array($driver, ['mariadb', 'mysql-mariadb'], true) ? 'utf8mb4_nopad_bin' : 'utf8mb4_0900_bin')
            ->and($lower)->toContain('primary_unique_packed')
            ->and($lower)->toContain('primary_global_unique_packed');

        $uniqueStatements = array_values(array_filter(
            $ddl,
            static fn (string $sql): bool => str_contains(mb_strtolower($sql), 'unique index')
                || str_contains(mb_strtolower($sql), 'add unique')
        ));

        expect($uniqueStatements)->not->toBeEmpty();

        $targetsHash = array_values(array_filter(
            $uniqueStatements,
            static fn (string $sql): bool => str_contains($sql, 'primary_unique_hash')
        ));

        expect($targetsHash)->not->toBeEmpty($driver . ' unique index must target primary_unique_hash');

        foreach ($targetsHash as $statement) {
            expect($statement)->not->toContain('`contactable_type`')
                ->and($statement)->not->toContain('`contactable_id`');
        }

        expect($lower)->not->toContain('char(512)')
            ->and($lower)->not->toContain('json_array')
            ->and($lower)->toContain('invisible');
    }
});

it('compiles social_profiles MySQL-family DDL with a virtual SHA-256 hash and ordinary unique index', function (): void {
    // Compiled-DDL verification only: same live-driver limit as above.
    // The PDO stub opens no database connection.
    $path = __DIR__ . '/../../../../packages/contacting/database/migrations/2000_01_01_000003_create_contact_social_profiles_table.php';

    foreach (['mysql', 'mariadb', 'mysql-mariadb'] as $driver) {
        $ddl = f02CompileMigrationDdl($path, $driver);
        $all = implode("\n", $ddl);
        $lower = mb_strtolower($all);

        expect($all)->toContain('SHA2(')
            ->and($all)->toContain('CONCAT(')
            ->and($all)->toContain('CHAR_LENGTH(')
            ->and($all)->toContain('`socialable_type`')
            ->and($all)->toContain('`socialable_id`')
            ->and($all)->toContain('`platform`')
            ->and($all)->toContain('`purpose`')
            ->and($all)->toContain('`is_primary`')
            ->and($all)->toContain('primary_unique_identity');

        expect($lower)->toContain(in_array($driver, ['mariadb', 'mysql-mariadb'], true) ? 'utf8mb4_nopad_bin' : 'utf8mb4_0900_bin')
            ->and($lower)->toContain('primary_unique_packed')
            ->and($lower)->toContain('primary_global_unique_packed');

        $uniqueStatements = array_values(array_filter(
            $ddl,
            static fn (string $sql): bool => str_contains(mb_strtolower($sql), 'unique index')
                || str_contains(mb_strtolower($sql), 'add unique')
        ));

        expect($uniqueStatements)->not->toBeEmpty();

        $targetsHash = array_values(array_filter(
            $uniqueStatements,
            static fn (string $sql): bool => str_contains($sql, 'primary_unique_identity')
        ));

        expect($targetsHash)->not->toBeEmpty($driver . ' unique index must target primary_unique_identity');

        foreach ($targetsHash as $statement) {
            expect($statement)->not->toContain('`socialable_type`')
                ->and($statement)->not->toContain('`platform`');
        }

        expect($lower)->not->toContain('char(512)')
            ->and($lower)->not->toContain('json_array');

        expect($lower)->toContain('invisible');
    }
});
