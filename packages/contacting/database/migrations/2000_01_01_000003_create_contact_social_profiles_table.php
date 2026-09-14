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

        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType, $tableName): void {
            $table->uuid('id')->primary();

            $table->nullableUuidMorphs('owner');
            $table->nullableUuidMorphs('socialable');

            $table->string('platform');
            $table->string('purpose')->default('general');
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
            $table->index(
                ['socialable_type', 'socialable_id', 'platform', 'purpose', 'is_primary', 'valid_from', 'valid_until', 'sort_order'],
                str_replace(['.', '-', ' '], '_', $tableName) . '_socialable_validity_index',
            );
        });

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

        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Contacting primary index migration cannot run on unsupported database driver [%s].',
                $driver,
            ));
        }

        if ($driver === 'mysql') {
            $wrappedColumns = implode(', ', array_map(
                $grammar->wrap(...),
                $columns,
            ));
            $expression = sprintf(
                'CASE WHEN %s = 1 AND %s IS NOT NULL AND %s IS NOT NULL '
                . 'THEN CAST(JSON_ARRAY(%s) AS CHAR(512)) ELSE NULL END',
                $grammar->wrap('is_primary'),
                $grammar->wrap($morphTypeColumn),
                $grammar->wrap($morphIdColumn),
                $wrappedColumns,
            );

            $connection->statement(sprintf(
                'CREATE UNIQUE INDEX %s ON %s ((%s))',
                $grammar->wrap($indexName),
                $grammar->wrapTable($tableName),
                $expression,
            ));

            return;
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
