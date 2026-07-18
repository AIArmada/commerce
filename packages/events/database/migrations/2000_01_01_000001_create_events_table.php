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

        Schema::create(config('events.database.tables.events', 'events'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->index(['owner_type', 'owner_id']);
            $table->string('created_by_type')->nullable()->index();
            $table->uuid('created_by_id')->nullable()->index();
            $table->index(['created_by_type', 'created_by_id']);
            $table->string('title');
            $table->string('slug')->nullable()->index();
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->nullable()->index();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->string('delivery_mode')->nullable()->index();
            $table->string('timezone')->nullable();
            $table->uuid('default_venue_id')->nullable()->index();
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestampTz('cancelled_at')->nullable()->index();
            $table->timestampTz('postponed_at')->nullable()->index();
            $table->timestampTz('archived_at')->nullable()->index();
            $table->timestampTz('completed_at')->nullable()->index();
            $table->timestampTz('last_state_change_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->text('status_message')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->string('schedule_kind', 32)->default('single')->index();
            $table->string('pricing_mode')->nullable()->index();
            $table->string('registration_mode')->nullable()->index();
            $table->boolean('issue_passes_for_free')->default(true);
        });
    }
};
