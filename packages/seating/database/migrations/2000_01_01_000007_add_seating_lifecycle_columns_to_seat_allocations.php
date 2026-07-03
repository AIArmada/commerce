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
            $table->uuid('seat_id')->nullable()->change();
            $table->uuid('seat_section_id')->nullable()->after('seat_id')->index();
            $table->string('released_by_type')->nullable()->after('revoked_at');
            $table->string('released_by_id')->nullable()->after('released_by_type');

            $table->index(['seat_section_id', 'state'], 'sa_section_state_idx');
            $table->index(['released_by_type', 'released_by_id'], 'sa_released_by_idx');
        });
    }
};
