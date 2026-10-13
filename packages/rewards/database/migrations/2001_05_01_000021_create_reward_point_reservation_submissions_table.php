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

        Schema::create(config('rewards.database.tables.point_reservation_submissions', 'reward_point_reservation_submissions'), function (Blueprint $table): void {

            $table->uuid('id')->primary();
            $table->string('reservation_key', 191)->unique();
            $table->string('status', 16)->index();
            $table->string('reference', 191)->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });
    }
};
