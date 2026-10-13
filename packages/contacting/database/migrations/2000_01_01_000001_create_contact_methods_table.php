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
        $tableName = config('contacting.database.tables.contact_methods', 'contact_methods');
        $jsonColumnType = commerce_json_column_type('contacting', 'json');
        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType, $tableName): void {
            $table->uuid('id')->primary();

            $table->nullableUuidMorphs('owner');
            $table->nullableUuidMorphs('contactable');

            $table->string('type');
            $table->string('purpose')->default('general');
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
