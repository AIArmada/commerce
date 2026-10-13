<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRIMARY_UNIQUE_HASH_COLUMN = 'primary_unique_hash';

    public function up(): void
    {
        $tableName = config('contacting.database.tables.contact_methods', 'contact_methods');
        $jsonColumnType = commerce_json_column_type('contacting', 'json');
        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();
        $driver = ConnectionDriver::name($connection);
        $isMysqlFamily = in_array($driver, ['mysql', 'mariadb'], true)
            || $connection instanceof MySqlConnection;
        $identityCollation = ConnectionDriver::isMariaDb($connection)
            ? 'utf8mb4_nopad_bin'
            : ($isMysqlFamily ? 'utf8mb4_0900_bin' : null);

        $hashColumn = self::PRIMARY_UNIQUE_HASH_COLUMN;
        $pack = static fn (array $columns): string => 'CONCAT(' . implode(', ', array_merge(...array_map(static fn (string $column): array => [
            sprintf('CHAR_LENGTH(%s)', $grammar->wrap($column)),
            "':'",
            $grammar->wrap($column),
            "'|'",
        ], $columns))) . ')';
        $ownerPackedColumn = 'primary_unique_packed';
        $globalPackedColumn = 'primary_global_unique_packed';
        $ownerPackExpression = $isMysqlFamily ? $pack(['owner_type', 'owner_id', 'contactable_type', 'contactable_id', 'type', 'purpose']) : '';
        $globalPackExpression = $isMysqlFamily ? $pack(['contactable_type', 'contactable_id', 'type', 'purpose']) : '';
        $primaryHashExpression = $isMysqlFamily
            ? sprintf('CASE WHEN %s = 1 THEN SHA2(%s, 256) ELSE NULL END', $grammar->wrap('is_primary'), $grammar->wrap($ownerPackedColumn))
            : '';
        $globalHashExpression = $isMysqlFamily
            ? sprintf('CASE WHEN %s = 1 AND %s IS NULL AND %s IS NULL THEN SHA2(%s, 256) ELSE NULL END', $grammar->wrap('is_primary'), $grammar->wrap('owner_type'), $grammar->wrap('owner_id'), $grammar->wrap($globalPackedColumn))
            : '';

        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType, $tableName, $isMysqlFamily, $hashColumn, $ownerPackedColumn, $globalPackedColumn, $ownerPackExpression, $globalPackExpression, $primaryHashExpression, $globalHashExpression, $identityCollation): void {
            $table->uuid('id')->primary();

            $ownerType = $table->string('owner_type', 191)->nullable();
            $ownerId = $table->uuid('owner_id')->nullable();
            $table->index(['owner_type', 'owner_id']);
            $contactableType = $table->string('contactable_type', 191)->nullable();
            $contactableId = $table->uuid('contactable_id')->nullable();
            $table->index(['contactable_type', 'contactable_id']);

            $type = $table->string('type', 191);
            $purpose = $table->string('purpose', 191)->default('general');

            if ($identityCollation !== null) {
                foreach ([$ownerType, $ownerId, $contactableType, $contactableId, $type, $purpose] as $column) {
                    $column->charset('utf8mb4')->collation($identityCollation);
                }
            }

            $table->string('label')->nullable();

            $table->text('value');
            $table->text('normalized_value')->nullable();
            $table->text('display_value')->nullable();

            $table->string('country_code', 2)->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_verified')->default(false);

            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            // MySQL-family primary uniqueness lives in a nullable VIRTUAL
            // generated column holding a SHA-256 hash of the length-prefixed
            // non-null primary tuple. The ordinary unique index below covers
            // it; NULL allows non-qualifying duplicates. A truncated
            // functional key would report false collisions on long tuples,
            // so both MySQL and MariaDB share this hash column instead.
            if ($isMysqlFamily) {
                $table->text($ownerPackedColumn)->nullable()->virtualAs($ownerPackExpression)->invisible();
                $table->text($globalPackedColumn)->nullable()->virtualAs($globalPackExpression)->invisible();
                $table->string($hashColumn, 64)->nullable()->virtualAs($primaryHashExpression)->invisible();
                $globalHashColumn = 'primary_global_unique_hash';
                $table->string($globalHashColumn, 64)->nullable()->virtualAs($globalHashExpression)->invisible();
                $baseName = str_replace(['.', '-', ' '], '_', $tableName);
                $table->unique($hashColumn, $baseName . '_primary_owner_unique');
                $table->unique($globalHashColumn, $baseName . '_primary_global_unique');
            }

            $table->index(['type', 'purpose']);
            $table->index(['is_primary']);
            $table->index(['is_public']);
            $table->index(['is_verified']);
            $table->index(['country_code']);
            $table->index(
                ['contactable_type', 'contactable_id', 'is_primary'],
                str_replace(['.', '-', ' '], '_', $tableName) . '_contactable_primary_index',
            );
            $table->index(
                ['contactable_type', 'contactable_id', 'type', 'purpose', 'is_primary', 'valid_from', 'valid_until', 'sort_order'],
                str_replace(['.', '-', ' '], '_', $tableName) . '_contactable_validity_index',
            );
        });

        if ($isMysqlFamily) {
            return;
        }

        $this->createPrimaryUniqueIndexes($tableName);
    }

    private function createPrimaryUniqueIndexes(string $tableName): void
    {
        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();
        $driver = ConnectionDriver::name($connection);
        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Contacting primary index migration cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }
        $base = str_replace(['.', '-', ' '], '_', $tableName);
        $isPrimary = $driver === 'pgsql'
            ? $grammar->wrap('is_primary') . ' IS TRUE'
            : $grammar->wrap('is_primary') . ' = 1';
        $contactablePresent = $grammar->wrap('contactable_type') . ' IS NOT NULL AND '
            . $grammar->wrap('contactable_id') . ' IS NOT NULL';
        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s AND %s AND %s IS NOT NULL AND %s IS NOT NULL',
            $grammar->wrap($base . '_primary_owner_unique'),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), ['owner_type', 'owner_id', 'contactable_type', 'contactable_id', 'type', 'purpose'])),
            $isPrimary,
            $contactablePresent,
            $grammar->wrap('owner_type'),
            $grammar->wrap('owner_id'),
        ));
        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s AND %s AND %s IS NULL AND %s IS NULL',
            $grammar->wrap($base . '_primary_global_unique'),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), ['contactable_type', 'contactable_id', 'type', 'purpose'])),
            $isPrimary,
            $contactablePresent,
            $grammar->wrap('owner_type'),
            $grammar->wrap('owner_id'),
        ));
    }
};
