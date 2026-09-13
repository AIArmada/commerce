<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('communications', 'jsonb');

        Schema::create(config('communications.database.tables.batches', 'communication_batches'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name')->nullable();
            $table->string('purpose')->nullable();
            $table->string('category');
            $table->string('status')->index();
            $table->string('idempotency_key')->nullable()->index();
            $table->string('laravel_batch_id')->nullable()->index();
            $table->integer('requested_count')->default(0);
            $table->integer('planned_count')->default(0);
            $table->integer('queued_count')->default(0);
            $table->integer('completed_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestampTz('scheduled_at')->nullable()->index();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'status']);
            $table->index(['owner_type', 'owner_id', 'category', 'created_at']);
        });
    }
};
