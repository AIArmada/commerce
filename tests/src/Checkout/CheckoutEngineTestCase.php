<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Checkout;

use AIArmada\CommerceSupport\SupportServiceProvider as CommerceSupportServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Opt-in PostgreSQL integration base case for Checkout claim races.
 *
 * Set `CHECKOUT_ENGINE_PGSQL_DATABASE` to a disposable `chkeng_` database;
 * host, port, and user can be set with the matching `_HOST`, `_PORT`, and
 * `_USER` variables. Tests skip when no database is configured.
 *
 * Every test provisions its own `chkeng_`-prefixed schema inside that
 * disposable database and points the engine connection's `search_path` at it,
 * so parallel workers and forked race children never share checkout tables or
 * the race ledger. The schema is dropped when the test tears down.
 *
 * The engine exercise targets the payment-claim machinery directly: only the
 * commerce-support provider and the checkout migration are loaded, so the
 * test database needs nothing beyond the checkout schema plus the race
 * ledger the base case provisions.
 */
abstract class CheckoutEngineTestCase extends Orchestra
{
    protected string $engineDriver = 'pgsql';

    protected string $engineConnection = 'engine_pgsql';

    private ?string $engineSchema = null;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/checkout/database/migrations');
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

        $connection = $this->engineConnectionConfig($this->engineParams());
        $app['config']->set('database.connections.' . $this->engineConnection, $connection);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
    }

    /**
     * Keep owner scoping and auditing inert: the engine database only
     * carries the checkout schema.
     *
     * @param  Application  $app
     */
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('checkout.owner.enabled', false);
        $app['config']->set('audit.enabled', false);
        $app['config']->set('activitylog.enabled', false);
    }

    protected function getPackageProviders($app): array
    {
        return [
            CommerceSupportServiceProvider::class,
        ];
    }

    /** Select the opt-in isolated PostgreSQL engine; call first in each engine test. */
    protected function useEngine(): void
    {
        $params = $this->engineParams();
        if ($params['database'] === '') {
            $this->markTestSkipped('Checkout PostgreSQL engine unverified: set CHECKOUT_ENGINE_PGSQL_DATABASE to a disposable chkeng_ database.');
        }

        $this->assertDisposableEngineDatabase($params['database']);

        $schema = $this->engineSchemaName();
        $this->engineSchema = $schema;

        config()->set('checkout.database.json_column_type', 'jsonb');
        config()->set('database.default', $this->engineConnection);
        config()->set('database.connections.' . $this->engineConnection . '.search_path', $schema);
        DB::purge($this->engineConnection);
        $this->provisionEngineDatabase($params);
        $this->provisionEngineSchema($params, $schema);

        $currentSchema = DB::connection($this->engineConnection)
            ->selectOne('select current_schema() as schema');
        if (($currentSchema->schema ?? null) !== $schema) {
            $this->fail('Engine connection did not adopt the per-test PostgreSQL schema.');
        }

        $this->migrateEngineOnce($params);
        $this->provisionRaceLedger();
    }

    protected function tearDown(): void
    {
        $schema = $this->engineSchema;
        $this->engineSchema = null;

        // Orchestra rolls back the migrations it registered against the
        // (engine) default connection during parent teardown, so the schema
        // must survive until that cleanup finishes. Drop it afterwards over a
        // fresh PDO handle because the application container is gone.
        parent::tearDown();

        if ($schema === null) {
            return;
        }

        try {
            $params = $this->engineParams();
            $pdo = new PDO(
                'pgsql:host=' . $params['host'] . ';port=' . $params['port'] . ';dbname=' . $params['database'],
                $params['user'],
            );
            $pdo->exec('DROP SCHEMA IF EXISTS "' . str_replace('"', '""', $schema) . '" CASCADE');
        } catch (Throwable) {
            // Best-effort cleanup: the disposable engine database can be
            // recreated, but a leaked schema must never fail the test.
        }
    }

    protected function assertDisposableEngineDatabase(string $database): void
    {
        if (! preg_match('/^chkeng_[A-Za-z0-9_]{1,41}$/', $database)) {
            $this->fail('Refusing engine work on a non-disposable database name: only chkeng_-prefixed names are allowed.');
        }
    }

    protected function assertDisposableEngineSchema(string $schema): void
    {
        if (! preg_match('/^chkeng_[A-Za-z0-9_]{1,55}$/', $schema)) {
            $this->fail('Refusing engine work on a non-disposable schema name: only chkeng_-prefixed names are allowed.');
        }
    }

    /**
     * Per-test schema key: the parallel worker token when Pest/ParaTest
     * provides one, plus fresh randomness so repeated tests in the same
     * worker never share tables.
     */
    protected function engineSchemaName(): string
    {
        $token = $_SERVER['UNIQUE_TEST_TOKEN'] ?? null;

        if (! is_string($token) || $token === '') {
            $token = getenv('UNIQUE_TEST_TOKEN') ?: null;
        }

        if (! is_string($token) || $token === '') {
            $token = $_SERVER['TEST_TOKEN'] ?? null;
        }

        if (! is_string($token) || $token === '') {
            $token = getenv('TEST_TOKEN') ?: null;
        }

        if (! is_string($token) || $token === '') {
            $token = 'solo';
        }

        $token = mb_strtolower((string) preg_replace('/[^A-Za-z0-9_]+/', '_', $token));
        $token = mb_trim($token, '_');
        $token = $token === '' ? 'solo' : mb_substr($token, 0, 24);

        return 'chkeng_t_' . $token . '_' . bin2hex(random_bytes(4));
    }

    /** The race ledger stands in for external gateway state: one row per accepted charge. */
    private function provisionRaceLedger(): void
    {
        Schema::connection($this->engineConnection)->dropIfExists('checkout_engine_creates');
        Schema::connection($this->engineConnection)->create('checkout_engine_creates', function ($table): void {
            $table->string('id', 36)->primary();
            $table->string('session_id', 36)->index();
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->string('idempotency_key')->nullable();
            $table->string('created_at')->nullable();
        });

        Schema::connection($this->engineConnection)->dropIfExists('checkout_engine_events');
        Schema::connection($this->engineConnection)->create('checkout_engine_events', function ($table): void {
            $table->string('id', 36)->primary();
            $table->string('session_id', 36)->index();
            $table->string('event');
            $table->string('step_identifier')->nullable();
        });
    }

    /** @param array{host: string, port: string, user: string, database: string} $params @return array<string, mixed> */
    private function engineConnectionConfig(array $params): array
    {
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

    /** @return array{host: string, port: string, user: string, database: string} */
    private function engineParams(): array
    {
        $prefix = 'CHECKOUT_ENGINE_PGSQL';
        $read = static fn (string $key, string $default): string => (string) ($_SERVER[$prefix . '_' . $key] ?? getenv($prefix . '_' . $key) ?: $default);

        return [
            'host' => $read('HOST', '127.0.0.1'),
            'port' => $read('PORT', '5432'),
            'user' => $read('USER', 'postgres'),
            'database' => mb_trim($read('DATABASE', '')),
        ];
    }

    /** @param array{host: string, port: string, user: string, database: string} $params */
    private function provisionEngineDatabase(array $params): void
    {
        $database = $params['database'];
        $this->assertDisposableEngineDatabase($database);
        $pdo = new PDO(
            'pgsql:host=' . $params['host'] . ';port=' . $params['port'] . ';dbname=postgres',
            $params['user'],
        );
        $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '" . str_replace("'", "''", $database) . "'")->fetchColumn();

        if ($exists === false) {
            try {
                $pdo->exec('CREATE DATABASE "' . str_replace('"', '""', $database) . '"');
            } catch (PDOException $exception) {
                $sqlState = (string) ($exception->errorInfo[0] ?? '');
                if ($sqlState !== '42P04' && $sqlState !== '23505') {
                    throw $exception;
                }
            }
        }
    }

    /** @param array{host: string, port: string, user: string, database: string} $params */
    private function provisionEngineSchema(array $params, string $schema): void
    {
        $this->assertDisposableEngineSchema($schema);

        $pdo = new PDO(
            'pgsql:host=' . $params['host'] . ';port=' . $params['port'] . ';dbname=' . $params['database'],
            $params['user'],
        );
        $pdo->exec('CREATE SCHEMA IF NOT EXISTS "' . str_replace('"', '""', $schema) . '"');
    }

    /** @param array{host: string, port: string, user: string, database: string} $params */
    private function migrateEngineOnce(array $params): void
    {
        $lockPath = sys_get_temp_dir() . '/chkeng-migrate-' . $params['database'] . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('Could not open Checkout PostgreSQL engine migration lock.');
        }

        try {
            flock($lock, LOCK_EX);
            if (! Schema::connection($this->engineConnection)->hasTable('checkout_sessions')) {
                // The checkout provider is not registered in this slim engine
                // case, so point the migrator at the package migrations
                // explicitly instead of relying on package discovery.
                $this->artisan('migrate', [
                    '--database' => $this->engineConnection,
                    '--path' => __DIR__ . '/../../../packages/checkout/database/migrations',
                    '--realpath' => true,
                ]);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
