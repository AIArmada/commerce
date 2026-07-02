<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('seating.database.tables.seat_allocations', 'seat_allocations'), function (Blueprint $table): void {
            $table->timestampTz('released_at')->nullable()->after('allocated_at');
            $table->timestampTz('revoked_at')->nullable()->after('released_at');
        });

        Schema::table(config('seating.database.tables.seat_holds', 'seat_holds'), function (Blueprint $table): void {
            $table->timestampTz('converted_at')->nullable()->after('expires_at');
        });
    }
};
