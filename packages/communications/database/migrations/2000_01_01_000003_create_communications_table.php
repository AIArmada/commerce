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
        $tableName = (string) config('communications.database.tables.communications', 'communications');

        $schema = Schema::connection($this->getConnection());
        $connection = $schema->getConnection();
        $driver = ConnectionDriver::name($connection);

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf(
                'Communication idempotency requires PostgreSQL or SQLite; database driver [%s] is not supported.',
                $driver,
            ));
        }

        $schema->create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('batch_id')->nullable()->index();
            $table->foreignUuid('thread_id')->nullable()->index();
            $table->foreignUuid('parent_id')->nullable()->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('sender_type')->nullable();
            $table->string('sender_id')->nullable();
            $table->string('direction');
            $table->string('category');
            $table->string('priority');
            $table->string('purpose')->nullable();
            $table->string('status')->index();
            $table->string('idempotency_key')->nullable()->index();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->timestampTz('scheduled_at')->nullable()->index();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('processing_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'scheduled_at']);
            $table->index(['owner_type', 'owner_id', 'status', 'created_at']);
            $table->index(['owner_type', 'owner_id', 'category', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['sender_type', 'sender_id']);
        });

        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (idempotency_key) WHERE idempotency_key IS NOT NULL AND owner_type IS NULL AND owner_id IS NULL',
            $grammar->wrap('communications_global_idempotency_unique'),
            $grammar->wrapTable($tableName),
        ));

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (owner_type, owner_id, idempotency_key) WHERE idempotency_key IS NOT NULL AND owner_type IS NOT NULL AND owner_id IS NOT NULL',
            $grammar->wrap('communications_owner_idempotency_unique'),
            $grammar->wrapTable($tableName),
        ));
    }
};
