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

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('affiliatable_type');
            $table->uuid('affiliatable_id');
            $table->uuid('institution_id')->nullable()->index();
            $table->string('affiliation_type', 50)->nullable();
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->index(['affiliatable_type', 'affiliatable_id']);
            $table->index(['affiliatable_type', 'affiliatable_id', 'is_primary'], 'affiliations_target_primary_index');
        });

        $driver = ConnectionDriver::name(Schema::getConnection());

        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Affiliations identity index cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();
        $columns = ['affiliatable_type', 'affiliatable_id'];

        if ($driver === 'mysql') {
            $connection->statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s ((CASE WHEN %s = 1 THEN CAST(CONCAT_WS(%s, %s) AS CHAR(512)) ELSE NULL END))',
                $grammar->wrap('affiliations_primary_unique'),
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
            $grammar->wrap('affiliations_primary_unique'),
            $grammar->wrapTable($tableName),
            implode(', ', array_map($grammar->wrap(...), $columns)),
            $predicate,
        ));
    }
};
