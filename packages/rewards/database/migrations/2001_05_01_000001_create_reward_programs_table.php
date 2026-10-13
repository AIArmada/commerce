<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('rewards', 'jsonb');

        Schema::create(config('rewards.database.tables.programs', 'reward_programs'), function (Blueprint $table): void {

            $table->uuid('id')->primary();
            $table->string('family_key', 64)->unique();
            $table->string('title', 191);
            $table->string('mode', 32)->index();
            $table->nullableUuidMorphs('owner');
            $table->string('purpose', 191);
            $table->string('status', 16)->index();
            $table->foreignUuid('current_version_id')->nullable()->index();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('active_at')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'mode'], 'reward_programs_status_mode_idx');
        });
    }
};
