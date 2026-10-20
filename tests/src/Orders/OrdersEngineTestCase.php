<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Orders;

use AIArmada\CommerceSupport\SupportServiceProvider as CommerceSupportServiceProvider;
use AIArmada\Orders\OrdersServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\ActionServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Opt-in PostgreSQL integration base case for Orders concurrency tests.
 *
 * Set `ORDERS_ENGINE_PGSQL_DATABASE` to a disposable `ordeng_` database;
 * host, port, and user can be set with the matching `_HOST`, `_PORT`, and
 * `_USER` variables. Tests skip when no database is configured.
 *
 * Owner scoping, auditing, notifications, and the inventory/affiliates
 * integrations are disabled so the engine exercise covers the payment,
 * refund, and outbox mechanics in isolation; the test database only needs
 * the orders migrations.
 */
abstract class OrdersEngineTestCase extends Orchestra
{
    protected string $engineDriver = 'pgsql';

    protected string $engineConnection = 'engine_pgsql';

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/orders/database/migrations');
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
     * Set the Orders integration switches before package providers register.
     *
     * OrdersServiceProvider reads the integration and notification toggles
     * while registering, which happens before defineEnvironment() runs, and
     * the package config merge keeps pre-existing values. The engine exercise
     * targets the payment/refund machinery, not the surrounding presentation
     * or integration layers.
     *
     * @param  Application  $app
     */
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('orders.owner.enabled', false);
        $app['config']->set('orders.audit.enabled', false);
        $app['config']->set('orders.integrations.inventory.enabled', false);
        $app['config']->set('orders.integrations.affiliates.enabled', false);
        $app['config']->set('orders.integrations.docs.enabled', false);
        $app['config']->set('orders.notifications.payment_confirmation.enabled', false);

        // Keep the owen-it auditing observer and spatie activity logging
        // inert: the engine database only carries the orders schema.
        $app['config']->set('audit.enabled', false);
        $app['config']->set('activitylog.enabled', false);
    }

    protected function getPackageProviders($app): array
    {
        // ActionServiceProvider is discovery-registered in host apps; it turns
        // the AsAction outbox commands into resolvable console commands.
        return [
            CommerceSupportServiceProvider::class,
            ActionServiceProvider::class,
            OrdersServiceProvider::class,
        ];
    }

    /** Select the opt-in isolated PostgreSQL engine; call first in each engine test. */
    protected function useEngine(): void
    {
        $params = $this->engineParams();
        if ($params['database'] === '') {
            $this->markTestSkipped('Orders PostgreSQL engine unverified: set ORDERS_ENGINE_PGSQL_DATABASE to a disposable ordeng_ database.');
        }

        $this->assertDisposableEngineDatabase($params['database']);
        config()->set('orders.database.json_column_type', 'jsonb');
        config()->set('database.default', $this->engineConnection);
        DB::purge($this->engineConnection);
        $this->provisionEngineDatabase($params);
        $this->migrateEngineOnce($params);
    }

    protected function assertDisposableEngineDatabase(string $database): void
    {
        if (! preg_match('/^ordeng_[A-Za-z0-9_]{1,41}$/', $database)) {
            $this->fail('Refusing engine work on a non-disposable database name: only ordeng_-prefixed names are allowed.');
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
        $prefix = 'ORDERS_ENGINE_PGSQL';
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
        $lockPath = sys_get_temp_dir() . '/ordeng-migrate-' . $params['database'] . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('Could not open Orders PostgreSQL engine migration lock.');
        }

        try {
            flock($lock, LOCK_EX);
            $ordersTable = (string) config('orders.database.tables.orders', 'orders');
            if (! Schema::connection($this->engineConnection)->hasTable($ordersTable)) {
                $this->artisan('migrate', ['--database' => $this->engineConnection]);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
