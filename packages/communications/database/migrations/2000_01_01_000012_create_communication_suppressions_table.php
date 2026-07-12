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

        Schema::create(config('communications.database.tables.suppressions', 'communication_suppressions'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('recipient_type')->nullable();
            $table->string('recipient_id')->nullable();
            $table->string('destination_hash')->nullable();
            $table->string('channel')->nullable();
            $table->string('category')->nullable();
            $table->string('reason');
            $table->string('source')->nullable();
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('lifted_at')->nullable();
            $table->string('created_by_type')->nullable();
            $table->string('created_by_id')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'destination_hash', 'channel']);
            $table->index(['owner_type', 'owner_id', 'recipient_type', 'recipient_id']);
        });
    }
};
