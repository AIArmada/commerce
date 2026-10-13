<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\AffiliateCashPosting;

use AIArmada\Affiliates\AffiliatesServiceProvider;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\AffiliateCashPosting\Support\CashPostingFailureInjector;
use AIArmada\Commerce\Tests\AffiliateCashPosting\Support\FixtureAffiliateAwardPolicy;
use AIArmada\CommerceSupport\SupportServiceProvider as CommerceSupportServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use LogicException;
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use RuntimeException;
use Spatie\LaravelData\LaravelDataServiceProvider;

/**
 * Isolated real-driver harness for the fixed-cash award foundation.
 *
 * Boots the original affiliates + commerce-support providers and runs
 * the ORIGINAL affiliates package migrations (never copied schema)
 * against worker-isolated databases on the existing isolated servers
 * only: PostgreSQL 127.0.0.1:45432 and MariaDB 127.0.0.1:43306. The
 * driver is selected with `CASH_POST_DRIVER=pgsql|mysql`; anything else
 * fails closed. Every guard refuses live endpoints (notably 5432) and
 * any database outside the `share_rewards_test_cash_post_*` namespace.
 * Set `CASH_POST_ENABLED=1` to opt in; ordinary package suites skip this
 * real-driver integration harness when the isolated database services are
 * not explicitly requested.
 *
 * Only migrations required by the award action are loaded. Contact tables
 * are outside this integration harness's persistence boundary.
 *
 * No RefreshDatabase: rows stay committed so spawned race workers in
 * independent OS processes observe them. Tests isolate by unique award
 * keys and per-test affiliates instead of global counts or truncates.
 */
abstract class CashPostingTestCase extends Orchestra
{
    public const DRIVER_PGSQL = 'pgsql';

    public const DRIVER_MYSQL = 'mysql';

    protected string $cashPostDriver = self::DRIVER_PGSQL;

    protected string $cashPostDatabase = '';

    private ?string $workerDatabaseOverride = null;

    /**
     * Boot the harness outside PHPUnit (race workers in own processes).
     */
    public function bootWorker(string $database): void
    {
        $this->workerDatabaseOverride = $database;
        $this->setUp();
    }

    public function cashPostDriverName(): string
    {
        return $this->cashPostDriver;
    }

    public function cashPostDatabaseName(): string
    {
        return $this->cashPostDatabase;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeRecipient(array $attributes = []): Affiliate
    {
        $suffix = mb_substr(str_replace('-', '', (string) Str::uuid()), 0, 12);

        return Affiliate::create(array_merge([
            'code' => 'CASHPOST' . mb_strtoupper($suffix),
            'name' => 'Cash Post Recipient ' . $suffix,
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ], $attributes));
    }

    protected function awardKey(string $suffix): string
    {
        return 'cashpost:test:' . $suffix . ':' . mb_substr(str_replace('-', '', (string) Str::uuid()), 0, 12);
    }

    protected function setUp(): void
    {
        if (getenv('CASH_POST_ENABLED') !== '1') {
            $this->markTestSkipped('Set CASH_POST_ENABLED=1 to run isolated real-driver cash-post integration tests.');
        }

        $this->cashPostDriver = $this->resolveDriver();
        $this->cashPostDatabase = $this->workerDatabaseOverride ?? $this->resolveWorkerDatabase($this->cashPostDriver);
        $this->assertGuardedEndpoint($this->cashPostDriver, $this->cashPostDatabase);

        parent::setUp();

        Model::reguard();

        CashPostingFailureInjector::disarm();
        FixtureAffiliateAwardPolicy::resetObservation();

        $this->ensureWorkerDatabaseExists($this->cashPostDriver, $this->cashPostDatabase);

        if (getenv('CASH_POST_SKIP_MIGRATE') !== '1') {
            $exit = Artisan::call('migrate', [
                '--database' => 'cashpost',
                '--path' => [
                    __DIR__ . '/../../../packages/affiliates/database/migrations',
                ],
                '--realpath' => true,
                '--force' => true,
            ]);

            if ($exit !== 0) {
                throw new RuntimeException('Cash-post harness migration failed: ' . Artisan::output());
            }
        }

        $this->assertGuardedEndpoint($this->cashPostDriver, $this->cashPostDatabase);
    }

    protected function tearDown(): void
    {
        CashPostingFailureInjector::disarm();
        FixtureAffiliateAwardPolicy::resetObservation();

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        $driver = $this->cashPostDriver !== '' ? $this->cashPostDriver : $this->resolveDriver();
        $database = $this->cashPostDatabase !== '' ? $this->cashPostDatabase : $this->resolveWorkerDatabase($driver);
        $this->assertGuardedEndpoint($driver, $database);

        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.timezone', 'UTC');

        $app['config']->set('database.default', 'cashpost');

        if ($driver === self::DRIVER_MYSQL) {
            $app['config']->set('database.connections.cashpost', [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 43306,
                'database' => $database,
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'timezone' => '+00:00',
            ]);
        } else {
            $app['config']->set('database.connections.cashpost', [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => 45432,
                'database' => $database,
                'username' => 'share_rewards_test',
                'password' => '',
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'timezone' => 'UTC',
            ]);
        }

        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');

        $app['config']->set('data.date_format', DATE_ATOM);

        // Auxiliary business-event logging only (same as the monorepo base
        // case): money tables stay fully real; activity/audit writes are
        // out of scope for cash-posting proof. Auditing is already inert
        // in console via the vendor default.
        $app['config']->set('activitylog.enabled', false);

        $app['config']->set('affiliates.owner.enabled', false);
        $app['config']->set('affiliates.owner.include_global', false);
        $app['config']->set('affiliates.registration.approval_mode', 'admin');
        $app['config']->set('affiliates.database.json_column_type', $driver === self::DRIVER_MYSQL ? 'json' : 'jsonb');
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            CommerceSupportServiceProvider::class,
            AffiliatesServiceProvider::class,
        ];
    }

    private function resolveDriver(): string
    {
        $driver = mb_strtolower((string) (getenv('CASH_POST_DRIVER') ?: self::DRIVER_PGSQL));

        if ($driver !== self::DRIVER_PGSQL && $driver !== self::DRIVER_MYSQL) {
            throw new LogicException('CASH_POST_DRIVER must be [pgsql] or [mysql].');
        }

        return $driver;
    }

    private function resolveWorkerDatabase(string $driver): string
    {
        $token = (string) (getenv('TEST_TOKEN') ?: 'local');
        $token = mb_strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $token));

        if ($token === '') {
            $token = 'local';
        }

        $prefix = $driver === self::DRIVER_MYSQL ? 'my' : 'pg';

        return "share_rewards_test_cash_post_{$prefix}_{$token}";
    }

    private function assertGuardedEndpoint(string $driver, string $database): void
    {
        if ($driver === self::DRIVER_MYSQL) {
            $port = 43306;
        } else {
            $port = 45432;
        }

        if ($port === 5432) {
            throw new LogicException('Cash-post harness refuses the live database port.');
        }

        if (! preg_match('/^share_rewards_test_cash_post_[a-z0-9_]+$/', $database)) {
            throw new LogicException(sprintf('Cash-post harness refuses database [%s]; guarded namespace only.', $database));
        }
    }

    private function ensureWorkerDatabaseExists(string $driver, string $database): void
    {
        if ($driver === self::DRIVER_MYSQL) {
            $pdo = new PDO('mysql:host=127.0.0.1;port=43306', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return;
        }

        $pdo = new PDO('pgsql:host=127.0.0.1;port=45432;dbname=postgres', 'share_rewards_test', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $check = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $check->execute([$database]);

        if ($check->fetchColumn() === false) {
            $pdo->exec('CREATE DATABASE "' . $database . '"');
        }
    }
}
