<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Tests\Core\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use PHPUnit\Framework\Assert;

/** Guarded PostgreSQL 17 harness for rewards-core concurrency proof. */
final class RewardCoreWorkerDb
{
    public const string WORKER_CONNECTION = 'rewards_core_worker';

    public const string BASE_CONNECTION = 'rewards_core_base';

    /** @return array<string, mixed> */
    public static function connectionConfig(?string $database = null): array
    {
        $config = [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 45432,
            'database' => 'share_rewards_test',
            'username' => 'share_rewards_test',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ];
        self::guardBaseConfig($config);
        if ($database !== null) {
            self::guardWorkerDatabaseName($database);
            $config['database'] = $database;
        }

        return $config;
    }

    /** @param array<string, mixed> $config */
    public static function guardBaseConfig(array $config): void
    {
        Assert::assertSame('pgsql', (string) ($config['driver'] ?? ''));
        Assert::assertSame('127.0.0.1', (string) ($config['host'] ?? ''), 'Isolated fixtures are loopback only.');
        Assert::assertSame(45432, (int) ($config['port'] ?? 0), 'Refusing a non-isolated database port.');
        Assert::assertSame('share_rewards_test', (string) ($config['database'] ?? ''), 'Refusing a non-isolated database.');
    }

    public static function guardWorkerDatabaseName(string $database): void
    {
        Assert::assertMatchesRegularExpression('/^share_rewards_test_rewards_core_[a-z0-9_]{1,30}$/', $database);
    }

    public static function workerDatabaseName(string $test): string
    {
        $token = ParallelTesting::token();
        $raw = ($token !== false ? $token : 'serial') . '_' . getmypid() . '_' . $test;
        $suffix = mb_strtolower((string) preg_replace('/[^a-z0-9]+/', '_', $raw));
        $name = 'share_rewards_test_rewards_core_' . mb_substr($suffix, 0, 30);
        self::guardWorkerDatabaseName($name);

        return $name;
    }

    public static function baseConnection(): Connection
    {
        config(['database.connections.' . self::BASE_CONNECTION => self::connectionConfig()]);
        DB::purge(self::BASE_CONNECTION);

        return DB::connection(self::BASE_CONNECTION);
    }

    public static function assertServerIdentity(Connection $connection): void
    {
        $version = (string) $connection->selectOne('select version() as version')->version;
        Assert::assertStringStartsWith('PostgreSQL 17.', $version, 'Expected isolated PostgreSQL 17.');
    }

    public static function dropWorkerDatabase(string $database): void
    {
        self::guardWorkerDatabaseName($database);
        self::baseConnection()->statement('DROP DATABASE IF EXISTS "' . $database . '"');
    }

    public static function createWorkerDatabase(string $test): string
    {
        $database = self::workerDatabaseName($test);
        $connection = self::baseConnection();
        self::assertServerIdentity($connection);
        self::dropWorkerDatabase($database);
        $connection->statement('CREATE DATABASE "' . $database . '"');

        return $database;
    }

    public static function useWorkerDatabase(string $database): string
    {
        self::guardWorkerDatabaseName($database);
        $config = self::connectionConfig($database);
        config(['database.connections.' . self::WORKER_CONNECTION => $config]);
        DB::purge(self::WORKER_CONNECTION);
        $previous = (string) DB::getDefaultConnection();
        DB::setDefaultConnection(self::WORKER_CONNECTION);

        return $previous;
    }

    public static function restoreDefaultConnection(string $previous): void
    {
        DB::setDefaultConnection($previous);
        DB::purge(self::WORKER_CONNECTION);
        DB::purge(self::BASE_CONNECTION);
    }

    public static function migrateWorkerDatabase(): void
    {
        $files = glob(__DIR__ . '/../../../../../packages/rewards/database/migrations/2001_05_01_*_create_reward_*.php');
        Assert::assertIsArray($files, 'Reward package migrations must exist.');
        sort($files);
        Assert::assertCount(30, $files, 'Exactly the thirty reward package migrations must run.');

        foreach ($files as $file) {
            $migration = require $file;
            $migration->up();
        }
    }

    /** @return array{0: string, 1: string} */
    public static function setUp(string $test): array
    {
        $database = self::createWorkerDatabase($test);
        $previous = self::useWorkerDatabase($database);
        config(['rewards.database.json_column_type' => 'jsonb']);
        self::migrateWorkerDatabase();

        return [$database, $previous];
    }

    public static function tearDown(string $database, string $previous): void
    {
        self::restoreDefaultConnection($previous);
        self::dropWorkerDatabase($database);
    }
}
