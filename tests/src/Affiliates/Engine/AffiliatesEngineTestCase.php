<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Affiliates\Engine;

use AIArmada\Affiliates\AffiliatesServiceProvider;
use AIArmada\Cart\CartServiceProvider;
use AIArmada\CommerceSupport\SupportServiceProvider as CommerceSupportServiceProvider;
use AIArmada\Contacting\ContactingServiceProvider;
use AIArmada\Links\LinksServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use PDOException;
use Throwable;

/**
 * Opt-in PostgreSQL integration base case for affiliates identity races.
 *
 * Set `AFFILIATES_ENGINE_PGSQL_DATABASE` to a disposable `affeng_` database;
 * host, port, and user can be set with the matching `_HOST`, `_PORT`, and
 * `_USER` variables. Tests skip when no database is configured.
 *
 * Every test provisions its own `affeng_`-prefixed schema inside that
 * disposable database and points the engine connection's `search_path` at it,
 * so parallel workers and forked race children never share affiliate tables.
 * The schema is dropped when the test tears down.
 *
 * No RefreshDatabase: per-test schema isolation replaces it, and the engine
 * runner forbids an open parent transaction.
 */
abstract class AffiliatesEngineTestCase extends Orchestra
{
    protected string $engineConnection = 'affiliates_engine_pgsql';

    private ?string $engineSchema = null;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../../packages/affiliates/database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');

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

        $app['config']->set('affiliates.owner.enabled', false);
        $app['config']->set('affiliates.owner.include_global', false);
        $app['config']->set('affiliates.registration.approval_mode', 'admin');
    }

    /**
     * Keep owner scoping inert: the engine database only carries the
     * affiliate schema.
     *
     * @param  Application  $app
     */
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('affiliates.owner.enabled', false);
        $app['config']->set('audit.enabled', false);
        $app['config']->set('activitylog.enabled', false);
    }

    protected function getPackageProviders($app): array
    {
        return [
            CommerceSupportServiceProvider::class,
            ContactingServiceProvider::class,
            CartServiceProvider::class,
            LinksServiceProvider::class,
            AffiliatesServiceProvider::class,
        ];
    }

    /** Select the opt-in isolated PostgreSQL engine; call first in each engine test. */
    protected function useEngine(): void
    {
        $params = $this->engineParams();
        if ($params['database'] === '') {
            $this->markTestSkipped('Affiliates PostgreSQL engine unverified: set AFFILIATES_ENGINE_PGSQL_DATABASE to a disposable affeng_ database.');
        }

        $this->assertDisposableEngineDatabase($params['database']);

        $schema = $this->engineSchemaName();
        $this->engineSchema = $schema;

        config()->set('affiliates.database.json_column_type', 'jsonb');
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

        $this->migrateEngine($params);
        $this->provisionEngineUsers();
    }

    protected function tearDown(): void
    {
        $schema = $this->engineSchema;
        $this->engineSchema = null;

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
        if (! preg_match('/^affeng_[A-Za-z0-9_]{1,41}$/', $database)) {
            $this->fail('Refusing engine work on a non-disposable database name: only affeng_-prefixed names are allowed.');
        }
    }

    protected function assertDisposableEngineSchema(string $schema): void
    {
        if (! preg_match('/^affeng_[A-Za-z0-9_]{1,55}$/', $schema)) {
            $this->fail('Refusing engine work on a non-disposable schema name: only affeng_-prefixed names are allowed.');
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

        return 'affeng_t_' . $token . '_' . bin2hex(random_bytes(4));
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
        $prefix = 'AFFILIATES_ENGINE_PGSQL';
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
    /** Claimant stand-ins: the claim stores morph strings, but race workers resolve them, so the table must exist. */
    private function provisionEngineUsers(): void
    {
        Schema::connection($this->engineConnection)->dropIfExists('users');
        Schema::connection($this->engineConnection)->create('users', function ($table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    private function migrateEngine(array $params): void
    {
        $this->artisan('migrate', [
            '--database' => $this->engineConnection,
            '--path' => __DIR__ . '/../../../../packages/affiliates/database/migrations',
            '--realpath' => true,
        ]);
    }
}
