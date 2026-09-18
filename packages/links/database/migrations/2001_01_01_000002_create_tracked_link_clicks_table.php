<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumnType = commerce_json_column_type('links', 'jsonb');

        Schema::create(config('links.database.tables.clicks', 'tracked_link_clicks'), function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('link_id');
            $table->nullableUuidMorphs('owner');
            $table->timestampTz('occurred_at');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type')->nullable();
            $table->string('device_brand')->nullable();
            $table->string('device_model')->nullable();
            $table->string('browser')->nullable();
            $table->string('browser_version', 50)->nullable();
            $table->string('os')->nullable();
            $table->string('os_version', 50)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->text('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->{$jsonColumnType}('properties')->nullable();
            $table->timestampsTz();

            $table->index(['link_id', 'occurred_at']);
            $table->index('occurred_at');
        });
    }
};
