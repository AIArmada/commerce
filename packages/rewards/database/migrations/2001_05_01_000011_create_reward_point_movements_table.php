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

        Schema::create(config('rewards.database.tables.point_movements', 'reward_point_movements'), function (Blueprint $table): void {

            $table->uuid('id')->primary();
            $table->foreignUuid('lot_id')->index();
            $table->string('kind', 16)->index();
            $table->bigInteger('delta');
            $table->string('operation_key', 191)->unique();
            $table->string('aggregate_operation_key', 191)->index();
            $table->foreignUuid('linked_movement_id')->nullable()->index();
            $table->string('reservation_key', 191)->nullable()->index();
            $table->foreignUuid('award_id')->nullable()->index();
            $table->string('actor_type', 64);
            $table->string('actor_id', 64)->nullable();
            $table->string('reason', 191);
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['lot_id', 'kind'], 'reward_movements_lot_kind_idx');
            $table->index(['aggregate_operation_key', 'lot_id'], 'reward_movements_aggregate_lot_idx');
        });
    }
};
