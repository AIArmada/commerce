<?php

declare(strict_types=1);

namespace AIArmada\Cart\Tests;

use AIArmada\Cart\CartServiceProvider;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\SupportServiceProvider as CommerceSupportServiceProvider;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Opt-in PostgreSQL integration base case for Cart concurrency tests.
 *
 * Set `CART_ENGINE_PGSQL_DATABASE` to a disposable `carteng_` database;
 * host, port, and user can be set with the matching `_HOST`, `_PORT`, and
 * `_USER` variables. Tests skip when no database is configured.
 */
abstract class CartEngineTestCase extends Orchestra
{
    protected string $engineDriver = 'pgsql';

    protected string $engineConnection = 'engine_pgsql';

    protected ?User $engineOwner = null;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/cart/database/migrations');
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
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('cart.storage', 'database');
        $app['config']->set('cart.database.table', 'carts');
        $app['config']->set('cart.database.json_column_type', 'jsonb');
        $app['config']->set('cart.money.default_currency', 'USD');
        // Workers construct owner-scoped storage explicitly; the CartMerged
        // event path would otherwise resolve container storage mid-race.
        $app['config']->set('cart.events', false);
    }

    protected function getPackageProviders($app): array
    {
        return [CommerceSupportServiceProvider::class, CartServiceProvider::class];
    }

    /** Select the opt-in isolated PostgreSQL engine; call first in each engine test. */
    protected function useEngine(): void
    {
        $params = $this->engineParams();
        if ($params['database'] === '') {
            $this->markTestSkipped('Cart PostgreSQL engine unverified: set CART_ENGINE_PGSQL_DATABASE to a disposable carteng_ database.');
        }

        $this->assertDisposableEngineDatabase($params['database']);
        config()->set('cart.database.json_column_type', 'jsonb');
        config()->set('database.default', $this->engineConnection);
        DB::purge($this->engineConnection);
        $this->provisionEngineDatabase($params);
        $this->migrateEngineOnce($params);

        $owner = User::query()->create([
            'name' => 'Engine Owner',
            'email' => 'carteng-' . Str::lower(Str::random(12)) . '@example.com',
            'password' => 'secret',
        ]);
        $this->engineOwner = $owner;
        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));
    }

    /**
     * Storage bound to the engine database for the given owner partition.
     *
     * A null owner targets the global partition (guest carts).
     */
    protected function engineStorage(?Model $owner = null): DatabaseStorage
    {
        $storage = new DatabaseStorage(DB::connection($this->engineConnection), 'carts');

        return $owner === null ? $storage : $storage->withOwner($owner);
    }

    protected function assertDisposableEngineDatabase(string $database): void
    {
        if (! preg_match('/^carteng_[A-Za-z0-9_]{1,41}$/', $database)) {
            $this->fail('Refusing engine work on a non-disposable database name: only carteng_-prefixed names are allowed.');
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
        $prefix = 'CART_ENGINE_PGSQL';
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
    private function migrateEngineOnce(array $params): void
    {
        $lockPath = sys_get_temp_dir() . '/carteng-migrate-' . $params['database'] . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('Could not open Cart PostgreSQL engine migration lock.');
        }

        try {
            flock($lock, LOCK_EX);
            $table = (string) config('cart.database.table', 'carts');
            if (! Schema::connection($this->engineConnection)->hasTable($table)) {
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
