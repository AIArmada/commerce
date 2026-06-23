<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('events.database.tables.event_sessions', 'event_sessions'), function (Blueprint $table): void {
            $table->string('pricing_mode')->nullable()->index();
            $table->string('registration_mode')->nullable()->index();
            $table->boolean('issue_passes_for_free')->default(true);
        });
    }
};
