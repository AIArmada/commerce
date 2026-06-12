<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('events.database.json_column_type', 'jsonb');

        Schema::create(config('events.database.tables.event_ticket_type_components', 'event_ticket_type_components'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_ticket_type_id')->index();
            $table->uuid('component_ticket_type_id')->index();
            $table->integer('quantity')->default(1);
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
