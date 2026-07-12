<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('ticketing', 'jsonb');

        Schema::create(config('ticketing.database.tables.pass_holders'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('pass_id')->index();
            $table->string('holder_type')->nullable();
            $table->uuid('holder_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestampTz('transferred_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['pass_id', 'holder_type', 'holder_id']);
            $table->index(['pass_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ticketing.database.tables.pass_holders'));
    }
};
