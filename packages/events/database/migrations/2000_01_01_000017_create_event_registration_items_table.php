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

        Schema::create(config('events.database.tables.event_registration_items', 'event_registration_items'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('event_occurrence_id')->nullable()->index();
            $table->uuid('event_session_id')->nullable()->index();
            $table->uuid('event_registration_id')->index();
            $table->uuid('ticket_type_id')->index();
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_price')->nullable();
            $table->bigInteger('total_price')->nullable();
            $table->string('currency')->nullable();
            $table->uuid('external_order_item_id')->nullable()->index();
            $table->string('external_order_item_type')->nullable()->index();
            $table->string('status')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
