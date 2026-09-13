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

        Schema::create(config('events.database.tables.event_management_assignment_requests', 'event_management_assignment_requests'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('manageable_type')->index();
            $table->string('manageable_id')->index();
            $table->string('requestor_type')->index();
            $table->string('requestor_id')->index();
            $table->string('reviewer_type')->nullable()->index();
            $table->string('reviewer_id')->nullable()->index();
            $table->string('status')->index();
            $table->string('requested_role')->nullable();
            $table->text('justification')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['manageable_type', 'manageable_id']);
            $table->index(['requestor_type', 'requestor_id', 'status']);
        });
    }
};
