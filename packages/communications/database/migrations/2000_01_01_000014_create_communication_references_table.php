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
        $jsonType = commerce_json_column_type('communications', 'jsonb');
        $tableName = (string) config('communications.database.tables.references', 'communication_references');

        $schema = Schema::connection($this->getConnection());
        $connection = $schema->getConnection();
        $driver = ConnectionDriver::name($connection);

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Communication reference uniqueness requires PostgreSQL or SQLite; database driver [%s] is not supported.',
                $driver,
            ));
        }

        $schema->create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('communication_id')->index();
            $table->string('reference_type');
            $table->string('reference_id');
            $table->string('role')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['reference_type', 'reference_id']);
        });

        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (communication_id, reference_type, reference_id) WHERE role IS NULL',
            $grammar->wrap('communication_references_null_role_unique'),
            $grammar->wrapTable($tableName),
        ));

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (communication_id, reference_type, reference_id, role) WHERE role IS NOT NULL',
            $grammar->wrap('communication_references_role_unique'),
            $grammar->wrapTable($tableName),
        ));
    }
};
