<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('events.database.tables.event_term_policies', 'event_term_policies');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_term_id')->index();
            $table->string('policy_code', 64);
            $table->boolean('is_enabled')->default(true);
            $table->timestampsTz();

            $table->unique(['event_term_id', 'policy_code']);
        });
    }
};
