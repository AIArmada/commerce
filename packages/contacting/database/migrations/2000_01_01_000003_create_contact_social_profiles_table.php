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
        $tableName = config('contacting.database.tables.social_profiles', 'social_profiles');
        $jsonColumnType = commerce_json_column_type('contacting', 'json');

        // MariaDB/MySQL enforce a 3072-byte index limit that the full-width
        // 8-column validity index exceeds (1071). Columns keep their
        // canonical widths and every index keeps its canonical name and
        // membership; only the MySQL-family validity index spells its long
        // string parts with prefix lengths (a non-unique lookup assist, so
        // prefixes change selectivity, never query results).
        $driver = ConnectionDriver::name(Schema::getConnection());
        $isMysqlFamily = in_array($driver, ['mysql', 'mariadb'], true);
        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();
        $identityCollation = ConnectionDriver::isMariaDb($connection)
            ? 'utf8mb4_nopad_bin'
            : ($isMysqlFamily ? 'utf8mb4_0900_bin' : null);
        $validityIndexName = str_replace(['.', '-', ' '], '_', $tableName) . '_socialable_validity_index';
        $primaryUniqueIdentity = null;
        $globalUniqueIdentity = null;
        $ownerPackedColumn = 'primary_unique_packed';
        $globalPackedColumn = 'primary_global_unique_packed';
        $ownerPackExpression = null;
        $globalPackExpression = null;

        if ($isMysqlFamily) {
            $pack = static fn (array $columns): string => 'CONCAT(' . implode(', ', array_merge(...array_map(
                static fn (string $column): array => [sprintf('CHAR_LENGTH(%s)', $grammar->wrap($column)), "':'", $grammar->wrap($column), "'|'"],
                $columns,
            ))) . ')';
            $ownerPackExpression = $pack(['owner_type', 'owner_id', 'socialable_type', 'socialable_id', 'platform', 'purpose']);
            $globalPackExpression = $pack(['socialable_type', 'socialable_id', 'platform', 'purpose']);
            $primaryUniqueIdentity = sprintf('CASE WHEN %s = 1 THEN SHA2(%s, 256) ELSE NULL END', $grammar->wrap('is_primary'), $grammar->wrap($ownerPackedColumn));
            $globalUniqueIdentity = sprintf('CASE WHEN %s = 1 AND %s IS NULL AND %s IS NULL THEN SHA2(%s, 256) ELSE NULL END', $grammar->wrap('is_primary'), $grammar->wrap('owner_type'), $grammar->wrap('owner_id'), $grammar->wrap($globalPackedColumn));
        }

        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType, $tableName, $isMysqlFamily, $primaryUniqueIdentity, $globalUniqueIdentity, $ownerPackedColumn, $globalPackedColumn, $ownerPackExpression, $globalPackExpression, $validityIndexName, $grammar, $identityCollation): void {
            $table->uuid('id')->primary();

            $ownerType = $table->string('owner_type', 191)->nullable();
            $ownerId = $table->uuid('owner_id')->nullable();
            $table->index(['owner_type', 'owner_id']);
            $socialableType = $table->string('socialable_type', 191)->nullable();
            $socialableId = $table->uuid('socialable_id')->nullable();
            $table->index(['socialable_type', 'socialable_id']);

            $platform = $table->string('platform', 191);
            $purpose = $table->string('purpose', 191)->default('general');
            if ($identityCollation !== null) {
                foreach ([$ownerType, $ownerId, $socialableType, $socialableId, $platform, $purpose] as $column) {
                    $column->charset('utf8mb4')->collation($identityCollation);
                }
            }
            $table->string('label')->nullable();

            $table->string('handle')->nullable();
            $table->text('url')->nullable();
            $table->text('normalized_url')->nullable();
            $table->string('display_name')->nullable();
            $table->string('external_id')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_verified')->default(false);

            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['platform', 'purpose']);
            $table->index(['is_primary']);
            $table->index(['is_public']);
            $table->index(['is_verified']);
            $table->index(
                ['socialable_type', 'socialable_id', 'is_primary'],
                str_replace(['.', '-', ' '], '_', $tableName) . '_socialable_primary_index',
            );

            if (! $isMysqlFamily) {
                $table->index(
                    ['socialable_type', 'socialable_id', 'platform', 'purpose', 'is_primary', 'valid_from', 'valid_until', 'sort_order'],
                    str_replace(['.', '-', ' '], '_', $tableName) . '_socialable_validity_index',
                );
            } else {
                $table->rawIndex(sprintf(
                    '%s(191), %s, %s(191), %s(191), %s, %s, %s, %s',
                    $grammar->wrap('socialable_type'),
                    $grammar->wrap('socialable_id'),
                    $grammar->wrap('platform'),
                    $grammar->wrap('purpose'),
                    $grammar->wrap('is_primary'),
                    $grammar->wrap('valid_from'),
                    $grammar->wrap('valid_until'),
                    $grammar->wrap('sort_order'),
                ), $validityIndexName);
            }

            if ($primaryUniqueIdentity !== null) {
                $table->text($ownerPackedColumn)->nullable()->virtualAs($ownerPackExpression)->invisible();
                $table->text($globalPackedColumn)->nullable()->virtualAs($globalPackExpression)->invisible();
                $table->string('primary_unique_identity', 64)->nullable()->virtualAs($primaryUniqueIdentity)->invisible();
                $table->string('primary_global_unique_identity', 64)->nullable()->virtualAs($globalUniqueIdentity)->invisible();
                $base = str_replace(['.', '-', ' '], '_', $tableName);
                $table->unique('primary_unique_identity', $base . '_primary_owner_unique');
                $table->unique('primary_global_unique_identity', $base . '_primary_global_unique');
            }
        });

        if ($isMysqlFamily) {
            return;
        }

        $this->createPrimaryUniqueIndex(
            $tableName,
            ['socialable_type', 'socialable_id', 'platform', 'purpose'],
            'socialable_type',
            'socialable_id',
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function createPrimaryUniqueIndex(
        string $tableName,
        array $columns,
        string $morphTypeColumn,
        string $morphIdColumn,
    ): void {
        $indexName = str_replace(['.', '-', ' '], '_', $tableName) . '_primary_unique';

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();
        $driver = ConnectionDriver::name($connection);

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Contacting primary index migration cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }

        $predicate = $driver === 'pgsql'
            ? sprintf(
                '%s IS TRUE AND %s IS NOT NULL AND %s IS NOT NULL',
                $grammar->wrap('is_primary'),
                $grammar->wrap($morphTypeColumn),
                $grammar->wrap($morphIdColumn),
            )
            : sprintf(
                '%s = 1 AND %s IS NOT NULL AND %s IS NOT NULL',
                $grammar->wrap('is_primary'),
                $grammar->wrap($morphTypeColumn),
                $grammar->wrap($morphIdColumn),
            );

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s',
            $grammar->wrap($indexName),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), $columns)),
            $predicate,
        ));
    }
};
