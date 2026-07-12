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

        Schema::create(config('ticketing.database.tables.ticket_types', 'ticket_types'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('ticketable_id');
            $table->string('ticketable_type');
            $table->string('name');
            $table->string('code')->index();
            $table->text('description')->nullable();
            $table->string('access_type')->index();
            $table->string('seating_mode')->nullable()->index();
            $table->bigInteger('price')->nullable();
            $table->string('currency')->nullable();
            $table->integer('admits_quantity')->default(1);
            $table->integer('min_quantity')->nullable();
            $table->integer('max_quantity')->nullable();
            $table->timestampTz('sales_starts_at')->nullable();
            $table->timestampTz('sales_ends_at')->nullable();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->integer('sort_order')->default(0)->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['ticketable_id', 'ticketable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ticketing.database.tables.ticket_types', 'ticket_types'));
    }
};
