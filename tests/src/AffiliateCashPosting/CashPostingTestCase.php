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
use Orchestra\Testbench\TestCase as Orchestra;
use PDO;
use RuntimeException;
use Spatie\LaravelData\LaravelDataServiceProvider;

/**
 * Isolated real-driver harness for the fixed-cash award foundation.
 *
 * Boots the original affiliates + commerce-support providers and runs
 * the ORIGINAL affiliates package migrations (never copied schema)
 * against worker-isolated PostgreSQL databases on 127.0.0.1:45432.
 * Every guard refuses live endpoints (notably 5432) and
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

        $this->cashPostDatabase = $this->workerDatabaseOverride ?? $this->resolveWorkerDatabase();
        $this->assertGuardedEndpoint($this->cashPostDatabase);

        parent::setUp();

        Model::reguard();

        CashPostingFailureInjector::disarm();
        FixtureAffiliateAwardPolicy::resetObservation();

        $this->ensureWorkerDatabaseExists($this->cashPostDatabase);

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

        $this->assertGuardedEndpoint($this->cashPostDatabase);
    }

    protected function tearDown(): void
    {
        CashPostingFailureInjector::disarm();
        FixtureAffiliateAwardPolicy::resetObservation();

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        $database = $this->cashPostDatabase !== '' ? $this->cashPostDatabase : $this->resolveWorkerDatabase();
        $this->assertGuardedEndpoint($database);

        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');
        $app['config']->set('app.timezone', 'UTC');

        $app['config']->set('database.default', 'cashpost');

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
        $app['config']->set('affiliates.database.json_column_type', 'jsonb');
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            CommerceSupportServiceProvider::class,
            AffiliatesServiceProvider::class,
        ];
    }

    private function resolveWorkerDatabase(): string
    {
        $token = (string) (getenv('TEST_TOKEN') ?: 'local');
        $token = mb_strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $token));

        if ($token === '') {
            $token = 'local';
        }

        return "share_rewards_test_cash_post_{$token}";
    }

    private function assertGuardedEndpoint(string $database): void
    {
        if (! preg_match('/^share_rewards_test_cash_post_[a-z0-9_]+$/', $database)) {
            throw new RuntimeException(sprintf('Cash-post harness refuses database [%s]; guarded namespace only.', $database));
        }
    }

    private function ensureWorkerDatabaseExists(string $database): void
    {
        $pdo = new PDO('pgsql:host=127.0.0.1;port=45432;dbname=postgres', 'share_rewards_test', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $check = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $check->execute([$database]);

        if ($check->fetchColumn() === false) {
            $pdo->exec('CREATE DATABASE "' . $database . '"');
        }
    }
}
