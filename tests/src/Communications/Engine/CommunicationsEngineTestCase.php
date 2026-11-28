<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Communications\Engine;

use AIArmada\CommerceSupport\SupportServiceProvider as CommerceSupportServiceProvider;
use AIArmada\Communications\CommunicationsServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use PDOException;
use Throwable;

abstract class CommunicationsEngineTestCase extends Orchestra
{
    protected string $engineConnection = 'communications_engine_pgsql';

    private ?string $engineSchema = null;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../../packages/communications/database/migrations');
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
        $app['config']->set('communications.features.owner.enabled', true);
        $app['config']->set('communications.features.owner.include_global', false);
        $app['config']->set('communications.features.owner.auto_assign_on_create', true);
    }

    /**
     * @param  Application  $app
     */
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('communications.features.owner.enabled', true);
        $app['config']->set('communications.features.owner.include_global', false);
        $app['config']->set('communications.features.owner.auto_assign_on_create', true);
        $app['config']->set('audit.enabled', false);
        $app['config']->set('activitylog.enabled', false);
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            CommerceSupportServiceProvider::class,
            CommunicationsServiceProvider::class,
        ];
    }

    protected function useEngine(): void
    {
        $params = $this->engineParams();

        if ($params['database'] === '') {
            $this->markTestSkipped('Communications PostgreSQL engine unverified: set COMMUNICATIONS_ENGINE_PGSQL_DATABASE to a disposable comeng_ database.');
        }

        $this->assertDisposableEngineDatabase($params['database']);

        $schema = $this->engineSchemaName();
        $this->engineSchema = $schema;

        config()->set('communications.database.json_column_type', 'jsonb');
        config()->set('database.default', $this->engineConnection);
        config()->set('database.connections.' . $this->engineConnection . '.search_path', $schema);
        config()->set('database.connections.' . $this->engineConnection . '.application_name', 'comeng_' . $schema);
        DB::purge($this->engineConnection);
        $this->provisionEngineDatabase($params);
        $this->provisionEngineSchema($params, $schema);

        $currentSchema = DB::connection($this->engineConnection)
            ->selectOne('select current_schema() as schema');

        if (($currentSchema->schema ?? null) !== $schema) {
            $this->fail('Engine connection did not adopt the per-test PostgreSQL schema.');
        }

        $this->migrateEngine();
        $this->provisionEngineUsers();
        $this->provisionRaceMarkers();
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
        }
    }

    protected function assertDisposableEngineDatabase(string $database): void
    {
        if (! preg_match('/^comeng_[A-Za-z0-9_]{1,41}$/', $database)) {
            $this->fail('Refusing engine work on a non-disposable database name: only comeng_-prefixed names are allowed.');
        }
    }

    protected function assertDisposableEngineSchema(string $schema): void
    {
        if (! preg_match('/^comeng_[A-Za-z0-9_]{1,55}$/', $schema)) {
            $this->fail('Refusing engine work on a non-disposable schema name: only comeng_-prefixed names are allowed.');
        }
    }

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

        return 'comeng_t_' . $token . '_' . bin2hex(random_bytes(4));
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array{host: string, port: string, user: string, database: string}
     */
    private function engineParams(): array
    {
        $prefix = 'COMMUNICATIONS_ENGINE_PGSQL';
        $read = static fn (string $key, string $default): string => (string) ($_SERVER[$prefix . '_' . $key] ?? getenv($prefix . '_' . $key) ?: $default);

        return [
            'host' => $read('HOST', '127.0.0.1'),
            'port' => $read('PORT', '5432'),
            'user' => $read('USER', 'postgres'),
            'database' => mb_trim($read('DATABASE', '')),
        ];
    }

    /**
     * @param  array{host: string, port: string, user: string, database: string}  $params
     */
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

    /**
     * @param  array{host: string, port: string, user: string, database: string}  $params
     */
    private function provisionEngineSchema(array $params, string $schema): void
    {
        $this->assertDisposableEngineSchema($schema);

        $pdo = new PDO(
            'pgsql:host=' . $params['host'] . ';port=' . $params['port'] . ';dbname=' . $params['database'],
            $params['user'],
        );
        $pdo->exec('CREATE SCHEMA IF NOT EXISTS "' . str_replace('"', '""', $schema) . '"');
    }

    private function migrateEngine(): void
    {
        $this->artisan('migrate', [
            '--database' => $this->engineConnection,
            '--path' => __DIR__ . '/../../../../packages/communications/database/migrations',
            '--realpath' => true,
        ]);
    }

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

    private function provisionRaceMarkers(): void
    {
        Schema::connection($this->engineConnection)->dropIfExists('communications_engine_markers');
        Schema::connection($this->engineConnection)->create('communications_engine_markers', function ($table): void {
            $table->uuid('id')->primary();
            $table->string('marker');
        });
    }
}
