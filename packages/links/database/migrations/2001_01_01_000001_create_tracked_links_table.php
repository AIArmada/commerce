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

        Schema::create(config('links.database.tables.links', 'tracked_links'), function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('destination_url');
            $table->{$jsonColumnType}('utm_defaults')->nullable();
            $table->unsignedInteger('max_clicks')->nullable();
            $table->unsignedBigInteger('total_clicks')->default(0);
            $table->unsignedBigInteger('human_clicks')->default(0);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampTz('first_clicked_at')->nullable();
            $table->timestampTz('last_clicked_at')->nullable();
            $table->timestampsTz();

            $table->index('expires_at');
            $table->index('deactivated_at');
        });
    }
};
