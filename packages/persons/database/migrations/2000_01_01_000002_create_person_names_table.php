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
        $tableName = (string) config('persons.database.tables.person_names', 'person_names');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id')->index();
            $table->string('name_type', 50);
            $table->string('full_name');
            $table->string('language_code', 10);
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->index(['person_id', 'name_type']);
            $table->index(['person_id', 'is_primary'], 'person_names_person_primary_index');
        });

        $driver = ConnectionDriver::name(Schema::getConnection());

        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Person names identity index cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();
        $columns = ['person_id', 'name_type', 'language_code'];

        if ($driver === 'mysql') {
            $connection->statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s ((CASE WHEN %s = 1 THEN CAST(CONCAT_WS(%s, %s) AS CHAR(512)) ELSE NULL END))',
                $grammar->wrap('person_names_primary_unique'),
                $grammar->wrapTable($tableName),
                $grammar->wrap('is_primary'),
                $connection->getPdo()->quote('|'),
                implode(', ', array_map($grammar->wrap(...), $columns)),
            ));

            return;
        }

        $predicate = $driver === 'pgsql'
            ? sprintf('%s IS TRUE', $grammar->wrap('is_primary'))
            : sprintf('%s = 1', $grammar->wrap('is_primary'));

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s',
            $grammar->wrap('person_names_primary_unique'),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), $columns)),
            $predicate,
        ));
    }
};
