<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Fresh-install equivalent of the legacy identity cutover: owner/global
 * partial unique indexes without the derived scope columns.
 */
final class ProductIdentityIndexes
{
    public static function owner(string $tableName, string $identityColumn): void
    {
        $driver = self::driver();

        self::partial(
            $tableName,
            self::name($tableName, $identityColumn) . '_owner_unique',
            ['owner_type', 'owner_id', $identityColumn],
            self::wrap('owner_type') . ' IS NOT NULL AND ' . self::wrap('owner_id') . ' IS NOT NULL AND ' . self::wrap($identityColumn) . ' IS NOT NULL',
            $driver,
        );
        self::partial(
            $tableName,
            self::name($tableName, $identityColumn) . '_global_unique',
            [$identityColumn],
            self::wrap('owner_type') . ' IS NULL AND ' . self::wrap('owner_id') . ' IS NULL AND ' . self::wrap($identityColumn) . ' IS NOT NULL',
            $driver,
        );
    }

    public static function categories(string $tableName): void
    {
        $driver = self::driver();
        $prefix = self::name($tableName, 'identity');
        $owner = self::wrap('owner_type') . ' IS NOT NULL AND ' . self::wrap('owner_id') . ' IS NOT NULL';
        $global = self::wrap('owner_type') . ' IS NULL AND ' . self::wrap('owner_id') . ' IS NULL';
        $parent = self::wrap('parent_id');
        $slug = self::wrap('slug');

        self::partial($tableName, $prefix . '_owner_root_unique', ['owner_type', 'owner_id', 'slug'], "{$owner} AND {$parent} IS NULL AND {$slug} IS NOT NULL", $driver);
        self::partial($tableName, $prefix . '_owner_child_unique', ['owner_type', 'owner_id', 'parent_id', 'slug'], "{$owner} AND {$parent} IS NOT NULL AND {$slug} IS NOT NULL", $driver);
        self::partial($tableName, $prefix . '_global_root_unique', ['slug'], "{$global} AND {$parent} IS NULL AND {$slug} IS NOT NULL", $driver);
        self::partial($tableName, $prefix . '_global_child_unique', ['parent_id', 'slug'], "{$global} AND {$parent} IS NOT NULL AND {$slug} IS NOT NULL", $driver);
    }

    public static function attributeValues(string $tableName): void
    {
        $driver = self::driver();
        $prefix = self::name($tableName, 'identity');

        self::partial(
            $tableName,
            $prefix . '_locale_unique',
            ['attribute_id', 'attributable_type', 'attributable_id', 'locale'],
            self::wrap('locale') . ' IS NOT NULL',
            $driver,
        );
        self::partial(
            $tableName,
            $prefix . '_default_locale_unique',
            ['attribute_id', 'attributable_type', 'attributable_id'],
            self::wrap('locale') . ' IS NULL',
            $driver,
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private static function partial(string $tableName, string $indexName, array $columns, string $predicate, string $driver): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();

        if ($driver === 'mysql') {
            $expressions = implode(', ', array_map(
                fn (string $column): string => sprintf(
                    '(CASE WHEN %s THEN %s ELSE NULL END)',
                    $predicate,
                    $grammar->wrap($column),
                ),
                $columns,
            ));

            $connection->statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s (%s)',
                $grammar->wrap($indexName),
                $grammar->wrapTable($tableName),
                $expressions,
            ));

            return;
        }

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS %s ON %s (%s) WHERE %s',
            $grammar->wrap($indexName),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), $columns)),
            $predicate,
        ));
    }

    private static function driver(): string
    {
        $driver = ConnectionDriver::name(Schema::getConnection());

        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Product identity migration cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }

        return $driver;
    }

    private static function wrap(string $column): string
    {
        return Schema::getConnection()->getQueryGrammar()->wrap($column);
    }

    private static function name(string $tableName, string $suffix): string
    {
        return str_replace(['.', '-', ' '], '_', $tableName) . '_' . $suffix;
    }
}
