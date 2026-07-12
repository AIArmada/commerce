<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('seating', 'json');

        Schema::create(config('seating.database.tables.seat_holds'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('held_by');
            $table->nullableMorphs('owner');
            $table->uuid('seat_id')->index();
            $table->string('reference')->nullable()->index();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('converted_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('seating.database.tables.seat_holds'));
    }
};
