<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('events', 'jsonb');

        Schema::create(config('events.database.tables.event_approval_requests', 'event_approval_requests'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('approvable_type')->index();
            $table->uuid('approvable_id')->index();
            $table->string('target_type')->nullable()->index();
            $table->uuid('target_id')->nullable()->index();
            $table->string('requested_by_type')->nullable()->index();
            $table->uuid('requested_by_id')->nullable()->index();
            $table->string('assigned_to_type')->nullable()->index();
            $table->uuid('assigned_to_id')->nullable()->index();
            $table->string('status')->index();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
