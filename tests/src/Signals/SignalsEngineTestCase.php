<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Signals;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\SupportServiceProvider as CommerceSupportServiceProvider;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\SignalsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Real-engine (MySQL/MariaDB/PostgreSQL) base case for Signals concurrency tests.
 *
 * Engines are enabled per run via uniquely named disposable databases:
 * SIGNALS_ENGINE_PGSQL_DATABASE (+ optional _HOST/_PORT/_USER) and
 * SIGNALS_ENGINE_MYSQL_DATABASE (+ optional _HOST/_PORT/_USER/_SOCKET).
 * Local trust auth only; no passwords are read. Engines without a
 * configured database are skipped and reported as unverified.
 *
 * Only disposable sigeng_-prefixed database names are ever provisioned or
 * migrated; anything else fails fast. Migrations always run from the current
 * package migrations: there is no alternate migration loader.
 *
 * No RefreshDatabase: parallel workers share one disposable database per
 * engine, so schema setup is flock-guarded and tests isolate by unique keys.
 */
abstract class SignalsEngineTestCase extends Orchestra
{
    protected string $engineDriver = '';

    protected string $engineConnection = '';

    protected string $engineSecondConnection = '';

    protected ?User $engineOwner = null;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/signals/database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.timezone', 'UTC');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        foreach (['pgsql', 'mysql'] as $driver) {
            $params = $this->engineParams($driver);

            $app['config']->set('database.connections.engine_' . $driver, $this->engineConnectionConfig($driver, $params));
            $app['config']->set('database.connections.engine_' . $driver . '_b', $this->engineConnectionConfig($driver, $params));
        }

        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');

        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('signals.owner.enabled', true);
        $app['config']->set('signals.owner.include_global', false);
        $app['config']->set('signals.owner.auto_assign_on_create', true);
        $app['config']->set('signals.integrations.browser.enabled', true);
        $app['config']->set('signals.integrations.browser.auto_register_middleware', true);
        $app['config']->set('signals.integrations.browser.middleware_group', 'web');
        $app['config']->set('signals.integrations.browser.auto_inject', false);
    }

    protected function getPackageProviders($app): array
    {
        return [
            CommerceSupportServiceProvider::class,
            SignalsServiceProvider::class,
        ];
    }

    /**
     * Select the engine under test. Must be the first call in each engine test.
     */
    protected function useEngine(string $engine): void
    {
        if (! in_array($engine, ['pgsql', 'mysql'], true)) {
            $this->fail('Unknown signals engine [' . $engine . '].');
        }

        $params = $this->engineParams($engine);

        if (($params['database'] ?? '') === '') {
            $prefix = $engine === 'pgsql' ? 'SIGNALS_ENGINE_PGSQL' : 'SIGNALS_ENGINE_MYSQL';

            $this->markTestSkipped('Signals engine [' . $engine . '] unverified: set ' . $prefix . '_DATABASE to a disposable sigeng_ database.');
        }

        $this->assertDisposableEngineDatabase($params['database']);

        $this->engineDriver = $engine;
        $this->engineConnection = 'engine_' . $engine;
        $this->engineSecondConnection = 'engine_' . $engine . '_b';

        config()->set('signals.database.json_column_type', $engine === 'mysql' ? 'json' : 'jsonb');
        config()->set('database.default', $this->engineConnection);

        DB::purge($this->engineConnection);
        DB::purge($this->engineSecondConnection);

        $this->provisionEngineDatabase($engine, $params);
        $this->migrateEngineOnce($engine, $params);

        $owner = User::query()->create([
            'name' => 'Engine Owner',
            'email' => 'sigeng-' . Str::lower(Str::random(12)) . '@example.com',
            'password' => 'secret',
        ]);

        $this->engineOwner = $owner;

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));
    }

    public function engineProperty(string $prefix): TrackedProperty
    {
        return TrackedProperty::query()->create([
            'name' => $prefix,
            'slug' => Str::slug($prefix) . '-' . $this->engineDriver . '-' . Str::lower(Str::random(8)),
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{version: string, isolation: string}
     */
    protected function engineVersionAndIsolation(): array
    {
        if ($this->engineDriver === 'pgsql') {
            $version = (string) (DB::selectOne('select version() as v')->v ?? '');
            $isolation = (string) (DB::selectOne('show transaction_isolation')->transaction_isolation ?? '');

            return ['version' => $version, 'isolation' => $isolation];
        }

        $version = (string) (DB::selectOne('SELECT VERSION() AS v')->v ?? '');
        $isolation = (string) (DB::selectOne('SELECT @@transaction_isolation AS iso')->iso ?? '');

        return ['version' => $version, 'isolation' => $isolation];
    }

    /**
     * Deliberately configure session isolation on both engine connections for
     * the named test. Only the listed levels are accepted.
     */
    protected function setEngineSessionIsolation(string $level): void
    {
        if (! in_array($level, ['REPEATABLE READ', 'READ COMMITTED'], true)) {
            $this->fail('Unsupported engine isolation [' . $level . '].');
        }

        foreach ([$this->engineConnection, $this->engineSecondConnection] as $connection) {
            if ($this->engineDriver === 'pgsql') {
                DB::connection($connection)->statement(
                    'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL ' . $level
                );

                continue;
            }

            DB::connection($connection)->statement('SET SESSION TRANSACTION ISOLATION LEVEL ' . $level);
        }
    }

    protected function assertDisposableEngineDatabase(string $database): void
    {
        if (! preg_match('/^sigeng_[A-Za-z0-9_]{1,41}$/', $database)) {
            $this->fail('Refusing engine work on a non-disposable database name: only sigeng_-prefixed names are allowed.');
        }
    }

    /**
     * @param  array{host: string, port: string, user: string, database: string, socket: string}  $params
     * @return array<string, mixed>
     */
    private function engineConnectionConfig(string $driver, array $params): array
    {
        if ($driver === 'pgsql') {
            return [
                'driver' => 'pgsql',
                'host' => $params['host'],
                'port' => $params['port'],
                'database' => $params['database'] !== '' ? $params['database'] : 'postgres',
                'username' => $params['user'],
                'password' => '',
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'prefer',
                'timezone' => 'UTC',
            ];
        }

        $config = [
            'driver' => 'mysql',
            'host' => $params['host'],
            'port' => $params['port'],
            'database' => $params['database'] !== '' ? $params['database'] : 'mysql',
            'username' => $params['user'],
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'timezone' => '+00:00',
        ];

        if ($params['socket'] !== '') {
            $config['unix_socket'] = $params['socket'];
        }

        return $config;
    }

    /**
     * @return array{host: string, port: string, user: string, database: string, socket: string}
     */
    private function engineParams(string $engine): array
    {
        $prefix = $engine === 'pgsql' ? 'SIGNALS_ENGINE_PGSQL' : 'SIGNALS_ENGINE_MYSQL';

        $read = static fn (string $key, string $default): string => (string) ($_SERVER[$prefix . '_' . $key] ?? getenv($prefix . '_' . $key) ?: $default);

        return [
            'host' => $read('HOST', '127.0.0.1'),
            'port' => $read('PORT', $engine === 'pgsql' ? '5432' : '3306'),
            'user' => $read('USER', $engine === 'pgsql' ? 'postgres' : 'root'),
            'database' => mb_trim($read('DATABASE', '')),
            'socket' => mb_trim($read('SOCKET', '')),
        ];
    }

    /**
     * @param  array{host: string, port: string, user: string, database: string, socket: string}  $params
     */
    private function provisionEngineDatabase(string $engine, array $params): void
    {
        $database = $params['database'];

        $this->assertDisposableEngineDatabase($database);

        if ($engine === 'pgsql') {
            $pdo = new PDO(
                'pgsql:host=' . $params['host'] . ';port=' . $params['port'] . ';dbname=postgres',
                $params['user']
            );

            $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '" . str_replace("'", "''", $database) . "'")->fetchColumn();

            if ($exists === false) {
                try {
                    $pdo->exec('CREATE DATABASE "' . str_replace('"', '""', $database) . '"');
                } catch (PDOException $e) {
                    $sqlState = (string) ($e->errorInfo[0] ?? '');

                    if ($sqlState !== '42P04' && $sqlState !== '23505') {
                        throw $e;
                    }
                }
            }

            return;
        }

        $dsn = $params['socket'] !== ''
            ? 'mysql:unix_socket=' . $params['socket']
            : 'mysql:host=' . $params['host'] . ';port=' . $params['port'];

        $pdo = new PDO($dsn, $params['user']);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $database) . '` CHARACTER SET utf8mb4');
    }

    /**
     * @param  array{host: string, port: string, user: string, database: string, socket: string}  $params
     */
    private function migrateEngineOnce(string $engine, array $params): void
    {
        $lockPath = sys_get_temp_dir() . '/sigeng-migrate-' . $engine . '-' . $params['database'] . '.lock';
        $lock = fopen($lockPath, 'c');

        if ($lock === false) {
            throw new RuntimeException('Could not open engine migration lock.');
        }

        try {
            flock($lock, LOCK_EX);

            $eventsTable = (new SignalEvent)->getTable();

            if (! Schema::connection($this->engineConnection)->hasTable($eventsTable)) {
                $this->artisan('migrate', ['--database' => $this->engineConnection]);
            }

            if (! Schema::connection($this->engineConnection)->hasTable('users')) {
                Schema::connection($this->engineConnection)->create('users', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('name');
                    $table->string('email')->unique();
                    $table->string('password');
                    $table->timestamps();
                });
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
