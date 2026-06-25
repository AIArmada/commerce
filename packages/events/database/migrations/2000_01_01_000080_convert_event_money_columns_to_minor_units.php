<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('events.database.tables.venue_facilities', 'venue_facilities'), function (Blueprint $table): void {
            $table->bigInteger('fee_amount')->nullable()->change();
        });

        Schema::table(config('events.database.tables.event_facilities', 'event_facilities'), function (Blueprint $table): void {
            $table->bigInteger('fee_amount')->nullable()->change();
        });

        Schema::table(config('events.database.tables.event_registrations', 'event_registrations'), function (Blueprint $table): void {
            $table->bigInteger('total_amount')->nullable()->change();
        });

        Schema::table(config('events.database.tables.event_registration_items', 'event_registration_items'), function (Blueprint $table): void {
            $table->bigInteger('unit_price')->nullable()->change();
            $table->bigInteger('total_price')->nullable()->change();
        });

        Schema::table(config('events.database.tables.event_ticket_types', 'event_ticket_types'), function (Blueprint $table): void {
            $table->bigInteger('price')->nullable()->change();
        });
    }
};
