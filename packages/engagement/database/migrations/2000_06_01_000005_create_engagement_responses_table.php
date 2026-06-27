<?php

/**
 * Creates the engagement_responses table.
 * Part of the aiarmada/engagement package.
 *
 * @see https://github.com/aiarmada/engagement/docs
 */
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('engagement.database.json_column_type', 'jsonb');
        Schema::create(config('engagement.database.tables.responses', 'responses'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('responder_type')->index();
            $table->uuid('responder_id')->index();
            $table->index(['responder_type', 'responder_id']);
            $table->string('respondable_type')->index();
            $table->uuid('respondable_id')->index();
            $table->index(['respondable_type', 'respondable_id']);
            $table->string('response_type')->index();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->timestampTz('responded_at')->nullable()->index();
            $table->timestampTz('changed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable();
            $table->string('source')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableMorphs('owner');
            $table->timestampsTz();
        });
    }
};
