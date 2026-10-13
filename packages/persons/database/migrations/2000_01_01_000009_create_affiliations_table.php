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
        $tableName = (string) config('persons.database.tables.affiliations', 'affiliations');
        $driver = ConnectionDriver::name(Schema::getConnection());

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Affiliations identity index cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();
        $columns = ['affiliatable_type', 'affiliatable_id'];
        $mysqlIdentity = null;
        $isMysqlFamily = in_array($driver, ['mysql', 'mariadb'], true) || $connection instanceof MySqlConnection;
        $isMaria = ConnectionDriver::isMariaDb($connection);
        $identityCollation = $isMaria ? 'utf8mb4_nopad_bin' : ($isMysqlFamily ? 'utf8mb4_0900_bin' : null);
        $mysqlPackedColumn = 'primary_unique_packed';
        $packed = 'CONCAT(' . implode(', ', array_merge(...array_map(
            static fn (string $column): array => [sprintf('CHAR_LENGTH(%s)', $grammar->wrap($column)), "':'", $grammar->wrap($column), "'|'"],
            $columns,
        ))) . ')';

        if ($isMysqlFamily) {
            $mysqlIdentity = sprintf(
                'CASE WHEN %s = 1 THEN SHA2(%s, 256) ELSE NULL END',
                $grammar->wrap('is_primary'),
                $grammar->wrap($mysqlPackedColumn),
            );
        }

        Schema::create($tableName, function (Blueprint $table) use ($mysqlIdentity, $identityCollation, $isMysqlFamily, $mysqlPackedColumn, $packed): void {
            $table->uuid('id')->primary();
            $affiliatableType = $table->string('affiliatable_type');
            $affiliatableId = $table->uuid('affiliatable_id');
            if ($identityCollation !== null) {
                $affiliatableType->charset('utf8mb4')->collation($identityCollation);
                $affiliatableId->charset('utf8mb4')->collation($identityCollation);
            }
            $table->uuid('institution_id')->nullable()->index();
            $table->string('affiliation_type', 50)->nullable();
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->index(['affiliatable_type', 'affiliatable_id']);
            $table->index(['affiliatable_type', 'affiliatable_id', 'is_primary'], 'affiliations_target_primary_index');

            if ($isMysqlFamily) {
                $table->text($mysqlPackedColumn)->nullable()->virtualAs($packed)->invisible();
            }
            if ($mysqlIdentity !== null) {
                $table->string('primary_unique_identity', 64)->nullable()->virtualAs($mysqlIdentity)->invisible();
                $table->unique('primary_unique_identity', 'affiliations_primary_unique');
            }
        });

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $predicate = $driver === 'pgsql'
            ? sprintf('%s IS TRUE', $grammar->wrap('is_primary'))
            : sprintf('%s = 1', $grammar->wrap('is_primary'));

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s',
            $grammar->wrap('affiliations_primary_unique'),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), $columns)),
            $predicate,
        ));
    }
};
