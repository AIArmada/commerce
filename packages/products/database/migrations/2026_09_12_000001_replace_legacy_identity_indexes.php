<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        $tables = [
            'products' => (string) config('products.database.tables.products', 'products'),
            'variants' => (string) config('products.database.tables.variants', 'product_variants'),
            'categories' => (string) config('products.database.tables.categories', 'product_categories'),
            'collections' => (string) config('products.database.tables.collections', 'product_collections'),
            'attribute_groups' => (string) config('products.database.tables.attribute_groups', 'product_attribute_groups'),
            'attributes' => (string) config('products.database.tables.attributes', 'product_attributes'),
            'attribute_sets' => (string) config('products.database.tables.attribute_sets', 'product_attribute_sets'),
            'attribute_values' => (string) config('products.database.tables.attribute_values', 'product_attribute_values'),
        ];

        foreach (['products', 'variants', 'collections', 'attribute_groups', 'attributes', 'attribute_sets'] as $tableKey) {
            $this->removeLegacyIdentityShape($tables[$tableKey], ['owner_scope']);
        }

        $this->removeLegacyIdentityShape($tables['categories'], ['owner_scope', 'parent_scope']);

        if (! Schema::hasTable($tables['attribute_values'])) {
            $hasIdentityTables = false;
        } else {
            $hasIdentityTables = true;
            $this->removeAttributeValueIdentityIndex($tables['attribute_values']);
        }

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                $hasIdentityTables = true;

                break;
            }
        }

        if (! $hasIdentityTables) {
            return;
        }

        $driver = ConnectionDriver::name(Schema::getConnection());

        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Product identity migration cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }

        $this->createOwnerIdentityIndexes($tables['products'], 'slug', $driver);
        $this->createOwnerIdentityIndexes($tables['products'], 'sku', $driver);
        $this->createOwnerIdentityIndexes($tables['variants'], 'sku', $driver);
        $this->createOwnerIdentityIndexes($tables['collections'], 'slug', $driver);
        $this->createOwnerIdentityIndexes($tables['attribute_groups'], 'code', $driver);
        $this->createOwnerIdentityIndexes($tables['attributes'], 'code', $driver);
        $this->createOwnerIdentityIndexes($tables['attribute_sets'], 'code', $driver);
        $this->createCategoryIdentityIndexes($tables['categories'], $driver);
        $this->createAttributeValueIdentityIndexes($tables['attribute_values'], $driver);
    }

    /**
     * @param  list<string>  $columns
     */
    private function removeLegacyIdentityShape(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach (Schema::getIndexes($tableName) as $index) {
            if (! ($index['unique'] ?? false)
                || ! is_array($index['columns'] ?? null)
                || ! array_intersect($columns, $index['columns'])) {
                continue;
            }

            $indexName = $index['name'] ?? null;

            if (! is_string($indexName) || $indexName === '') {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropUnique($indexName);
            });
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    private function removeAttributeValueIdentityIndex(string $tableName): void
    {
        if (! Schema::hasIndex($tableName, 'attr_val_unique')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropUnique('attr_val_unique');
        });
    }

    private function createOwnerIdentityIndexes(string $tableName, string $identityColumn, string $driver): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $ownerPredicate = sprintf(
            '%s IS NOT NULL AND %s IS NOT NULL AND %s IS NOT NULL',
            $this->wrap('owner_type'),
            $this->wrap('owner_id'),
            $this->wrap($identityColumn),
        );
        $globalPredicate = sprintf(
            '%s IS NULL AND %s IS NULL AND %s IS NOT NULL',
            $this->wrap('owner_type'),
            $this->wrap('owner_id'),
            $this->wrap($identityColumn),
        );
        $indexPrefix = $this->indexName($tableName, $identityColumn);

        $this->createPartialUniqueIndex(
            $tableName,
            $indexPrefix . '_owner_unique',
            ['owner_type', 'owner_id', $identityColumn],
            $ownerPredicate,
            $driver,
        );
        $this->createPartialUniqueIndex(
            $tableName,
            $indexPrefix . '_global_unique',
            [$identityColumn],
            $globalPredicate,
            $driver,
        );
    }

    private function createCategoryIdentityIndexes(string $tableName, string $driver): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $prefix = $this->indexName($tableName, 'identity');
        $owner = $this->wrap('owner_type') . ' IS NOT NULL AND ' . $this->wrap('owner_id') . ' IS NOT NULL';
        $global = $this->wrap('owner_type') . ' IS NULL AND ' . $this->wrap('owner_id') . ' IS NULL';
        $parent = $this->wrap('parent_id');
        $slug = $this->wrap('slug');

        $this->createPartialUniqueIndex(
            $tableName,
            $prefix . '_owner_root_unique',
            ['owner_type', 'owner_id', 'slug'],
            "{$owner} AND {$parent} IS NULL AND {$slug} IS NOT NULL",
            $driver,
        );
        $this->createPartialUniqueIndex(
            $tableName,
            $prefix . '_owner_child_unique',
            ['owner_type', 'owner_id', 'parent_id', 'slug'],
            "{$owner} AND {$parent} IS NOT NULL AND {$slug} IS NOT NULL",
            $driver,
        );
        $this->createPartialUniqueIndex(
            $tableName,
            $prefix . '_global_root_unique',
            ['slug'],
            "{$global} AND {$parent} IS NULL AND {$slug} IS NOT NULL",
            $driver,
        );
        $this->createPartialUniqueIndex(
            $tableName,
            $prefix . '_global_child_unique',
            ['parent_id', 'slug'],
            "{$global} AND {$parent} IS NOT NULL AND {$slug} IS NOT NULL",
            $driver,
        );
    }

    private function createAttributeValueIdentityIndexes(string $tableName, string $driver): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $prefix = $this->indexName($tableName, 'identity');

        $this->createPartialUniqueIndex(
            $tableName,
            $prefix . '_locale_unique',
            ['attribute_id', 'attributable_type', 'attributable_id', 'locale'],
            $this->wrap('locale') . ' IS NOT NULL',
            $driver,
        );
        $this->createPartialUniqueIndex(
            $tableName,
            $prefix . '_default_locale_unique',
            ['attribute_id', 'attributable_type', 'attributable_id'],
            $this->wrap('locale') . ' IS NULL',
            $driver,
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function createPartialUniqueIndex(
        string $tableName,
        string $indexName,
        array $columns,
        string $predicate,
        string $driver,
    ): void {
        if (Schema::hasIndex($tableName, $indexName)) {
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

    private function wrap(string $column): string
    {
        return Schema::getConnection()->getQueryGrammar()->wrap($column);
    }

    private function indexName(string $tableName, string $suffix): string
    {
        return str_replace(['.', '-', ' '], '_', $tableName) . '_' . $suffix;
    }
};
