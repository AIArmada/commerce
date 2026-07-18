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

        Schema::create(config('communications.database.tables.destinations', 'communication_destinations'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('recipient_type');
            $table->uuid('recipient_id');
            $table->string('channel')->index();
            $table->string('address')->nullable();
            $table->string('external_id')->nullable();
            $table->string('status')->default('active')->index();
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('verified_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->string('platform', 32)->nullable()->index();
            $table->string('app_version', 50)->nullable();
            $table->string('device_label', 255)->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestampTz('last_seen_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['recipient_type', 'recipient_id']);
        });
    }
};
