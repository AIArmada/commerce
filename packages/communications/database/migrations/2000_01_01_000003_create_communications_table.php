<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('communications.database.json_column_type', 'jsonb');

        Schema::create(config('communications.database.tables.communications', 'communications'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
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

            $table->index(['owner_type', 'owner_id', 'status', 'created_at']);
            $table->index(['owner_type', 'owner_id', 'category', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['sender_type', 'sender_id']);
        });
    }
};
