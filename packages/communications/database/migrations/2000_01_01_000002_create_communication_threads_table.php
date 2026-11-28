<?php

declare(strict_types=1);

use AIArmada\Communications\Features\Aggregate\Persistence\CommunicationThreadIdentityIndexes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('communications', 'jsonb');
        $tableName = config('communications.database.tables.threads', 'communication_threads');

        CommunicationThreadIdentityIndexes::assertSupportedDriver();

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('external_thread_id')->nullable();
            $table->string('channel')->nullable();
            $table->string('title')->nullable();
            $table->string('status')->index();
            $table->timestampTz('opened_at');
            $table->timestampTz('last_communication_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'status', 'last_communication_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        CommunicationThreadIdentityIndexes::install($tableName);
    }
};
