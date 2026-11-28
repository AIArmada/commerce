<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Persistence;

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class CommunicationThreadIdentityIndexes
{
    public static function assertSupportedDriver(): void
    {
        $driver = ConnectionDriver::name(Schema::getConnection());

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Communications thread identity migration cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }
    }

    public static function install(string $tableName): void
    {
        self::assertSupportedDriver();

        $base = str_replace(['.', '-', ' '], '_', $tableName);

        self::create(
            $tableName,
            self::indexName($base . '_thread_owner_unique'),
            ['owner_type', 'owner_id', 'channel', 'external_thread_id'],
            self::predicate([
                'owner_type' => 'IS NOT NULL',
                'owner_id' => 'IS NOT NULL',
                'channel' => 'IS NOT NULL',
                'external_thread_id' => 'IS NOT NULL',
            ]),
        );

        self::create(
            $tableName,
            self::indexName($base . '_thread_global_unique'),
            ['channel', 'external_thread_id'],
            self::predicate([
                'owner_type' => 'IS NULL',
                'owner_id' => 'IS NULL',
                'channel' => 'IS NOT NULL',
                'external_thread_id' => 'IS NOT NULL',
            ]),
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private static function create(string $tableName, string $indexName, array $columns, string $predicate): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS %s ON %s (%s) WHERE %s',
            $grammar->wrap($indexName),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), $columns)),
            $predicate,
        ));
    }

    /**
     * @param  array<string, string>  $conditions
     */
    private static function predicate(array $conditions): string
    {
        $grammar = Schema::getConnection()->getQueryGrammar();
        $parts = [];

        foreach ($conditions as $column => $operator) {
            $parts[] = $grammar->wrap($column) . ' ' . $operator;
        }

        return implode(' AND ', $parts);
    }

    private static function indexName(string $name): string
    {
        if (mb_strlen($name, '8bit') <= 63) {
            return $name;
        }

        return \mb_rtrim(mb_strcut($name, 0, 54, 'UTF-8'), '_') . '_' . \mb_substr(hash('sha256', $name), 0, 8);
    }
}
