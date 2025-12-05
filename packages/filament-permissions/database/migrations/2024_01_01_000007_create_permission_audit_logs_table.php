<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-permissions.database.tables.audit_logs', 'perm_audit_logs');
        $jsonType = config('filament-permissions.database.json_column_type', 'json');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('event_type');
            $table->string('severity');
            $table->uuidMorphs('actor');
            $table->nullableUuidMorphs('subject');
            $table->nullableUuidMorphs('target');
            $table->string('target_name')->nullable();
            $table->{$jsonType}('old_value')->nullable();
            $table->{$jsonType}('new_value')->nullable();
            $table->{$jsonType}('context')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('event_type');
            $table->index('severity');
            $table->index(['actor_type', 'actor_id'], 'audit_logs_actor_index');
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
            $table->index(['target_type', 'target_id'], 'audit_logs_target_index');
            $table->index('occurred_at');
            $table->index('session_id');
            $table->index(['event_type', 'occurred_at'], 'audit_logs_event_occurred_index');
            $table->index(['actor_type', 'actor_id', 'occurred_at'], 'audit_logs_actor_occurred_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-permissions.database.tables.audit_logs', 'perm_audit_logs'));
    }
};
