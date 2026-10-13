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

        Schema::create(config('rewards.database.tables.benefit_stock_movements', 'reward_benefit_stock_movements'), function (Blueprint $table): void {

            $table->uuid('id')->primary();
            $table->string('movement_key', 191)->unique();
            $table->foreignUuid('item_id')->index();
            $table->string('kind', 16)->index();
            $table->foreignUuid('grant_id')->nullable()->index();
            $table->unsignedBigInteger('quantity');
            $table->string('actor_type', 32);
            $table->string('actor_id', 191)->nullable();
            $table->string('actor_role', 16);
            $table->timestampTz('decided_at');
            $table->timestampsTz();
            $table->index(['item_id', 'kind']);
        });
    }
};
