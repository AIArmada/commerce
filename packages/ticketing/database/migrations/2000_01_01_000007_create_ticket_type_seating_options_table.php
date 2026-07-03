<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('ticketing.database.json_column_type', 'jsonb');

        Schema::create(config('ticketing.database.tables.ticket_type_seating_options'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('ticket_type_id')->index();
            $table->uuid('seat_section_id')->nullable()->index();
            $table->string('seat_category')->nullable()->index();
            $table->integer('included_quantity')->nullable();
            $table->integer('allowed_quantity')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
