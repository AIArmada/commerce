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
 * Opt-in PostgreSQL integration base case for Signals concurrency tests.
 *
 * Set `SIGNALS_ENGINE_PGSQL_DATABASE` to a disposable `sigeng_` database;
 * host, port, and user can be set with the matching `_HOST`, `_PORT`, and
 * `_USER` variables. Tests skip when no database is configured.
 */
abstract class SignalsEngineTestCase extends Orchestra
{
    protected string $engineDriver = 'pgsql';

    protected string $engineConnection = 'engine_pgsql';

    protected string $engineSecondConnection = 'engine_pgsql_b';

    protected ?User $engineOwner = null;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../../packages/signals/database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $params = $this->engineParams();
        $connection = $this->engineConnectionConfig($params);
        $app['config']->set('database.connections.'.$this->engineConnection, $connection);
        $app['config']->set('database.connections.'.$this->engineSecondConnection, $connection);
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
        return [CommerceSupportServiceProvider::class, SignalsServiceProvider::class];
    }

    /** Select the opt-in isolated PostgreSQL engine; call first in each engine test. */
    protected function useEngine(): void
    {
        $params = $this->engineParams();
        if ($params['database'] === '') {
            $this->markTestSkipped('Signals PostgreSQL engine unverified: set SIGNALS_ENGINE_PGSQL_DATABASE to a disposable sigeng_ database.');
        }

        $this->assertDisposableEngineDatabase($params['database']);
        config()->set('signals.database.json_column_type', 'jsonb');
        config()->set('database.default', $this->engineConnection);
        DB::purge($this->engineConnection);
        DB::purge($this->engineSecondConnection);
        $this->provisionEngineDatabase($params);
        $this->migrateEngineOnce($params);

        $owner = User::query()->create([
            'name' => 'Engine Owner',
            'email' => 'sigeng-'.Str::lower(Str::random(12)).'@example.com',
            'password' => 'secret',
        ]);
        $this->engineOwner = $owner;
        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));
    }

    public function engineProperty(string $prefix): TrackedProperty
    {
        return TrackedProperty::query()->create([
            'name' => $prefix,
            'slug' => Str::slug($prefix).'-pgsql-'.Str::lower(Str::random(8)),
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    }

    /** @return array{version: string, isolation: string} */
    protected function engineVersionAndIsolation(): array
    {
        $version = (string) (DB::selectOne('select version() as v')->v ?? '');
        $isolation = (string) (DB::selectOne('show transaction_isolation')->transaction_isolation ?? '');

        return ['version' => $version, 'isolation' => $isolation];
    }

    /** Deliberately configure session isolation on both PostgreSQL connections. */
    protected function setEngineSessionIsolation(string $level): void
    {
        if (! in_array($level, ['REPEATABLE READ', 'READ COMMITTED'], true)) {
            $this->fail('Unsupported PostgreSQL isolation ['.$level.'].');
        }

        foreach ([$this->engineConnection, $this->engineSecondConnection] as $connection) {
            DB::connection($connection)->statement('SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL '.$level);
        }
    }

    protected function assertDisposableEngineDatabase(string $database): void
    {
        if (! preg_match('/^sigeng_[A-Za-z0-9_]{1,41}$/', $database)) {
            $this->fail('Refusing engine work on a non-disposable database name: only sigeng_-prefixed names are allowed.');
        }
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
        $prefix = 'SIGNALS_ENGINE_PGSQL';
        $read = static fn (string $key, string $default): string => (string) ($_SERVER[$prefix.'_'.$key] ?? getenv($prefix.'_'.$key) ?: $default);

        return [
            'host' => $read('HOST', '127.0.0.1'),
            'port' => $read('PORT', '45432'),
            'user' => $read('USER', 'share_rewards_test'),
            'database' => mb_trim($read('DATABASE', '')),
        ];
    }

    /** @param array{host: string, port: string, user: string, database: string} $params */
    private function provisionEngineDatabase(array $params): void
    {
        $database = $params['database'];
        $this->assertDisposableEngineDatabase($database);
        $pdo = new PDO(
            'pgsql:host='.$params['host'].';port='.$params['port'].';dbname=postgres',
            $params['user'],
        );
        $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '".str_replace("'", "''", $database)."'")->fetchColumn();

        if ($exists === false) {
            try {
                $pdo->exec('CREATE DATABASE "'.str_replace('"', '""', $database).'"');
            } catch (PDOException $exception) {
                $sqlState = (string) ($exception->errorInfo[0] ?? '');
                if ($sqlState !== '42P04' && $sqlState !== '23505') {
                    throw $exception;
                }
            }
        }
    }

    /** @param array{host: string, port: string, user: string, database: string} $params */
    private function migrateEngineOnce(array $params): void
    {
        $lockPath = sys_get_temp_dir().'/sigeng-migrate-'.$params['database'].'.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('Could not open PostgreSQL engine migration lock.');
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
