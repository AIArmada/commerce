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

        Schema::create(config('rewards.database.tables.benefit_items', 'reward_benefit_items'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->index();
            $table->string('benefit_key', 64);
            $table->string('title', 191);
            $table->{$jsonType}('conditions');
            $table->unsignedBigInteger('cost_points')->nullable();
            $table->string('cost_unit', 16)->nullable();
            $table->boolean('direct_entitlement');
            $table->unsignedBigInteger('capacity');
            $table->{$jsonType}('fulfillment_config')->nullable();
            $table->string('status', 16)->index();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->timestampsTz();
            $table->unique(['program_id', 'benefit_key']);
        });
    }
};
