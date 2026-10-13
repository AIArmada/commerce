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

        Schema::create(config('rewards.database.tables.benefit_grants', 'reward_benefit_grants'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->string('grant_key', 191)->unique();
            $table->foreignUuid('item_id')->index();
            $table->string('recipient_type', 191);
            $table->string('recipient_id', 191);
            $table->foreignUuid('award_id')->nullable()->index();
            $table->string('reservation_key', 191)->nullable()->index();
            $table->unsignedBigInteger('quantity');
            $table->string('status', 16)->index();
            $table->string('fulfillment_adapter', 16);
            $table->string('external_ref', 191)->nullable();
            $table->{$jsonType}('evidence')->nullable();
            $table->string('actor_type', 32);
            $table->string('actor_id', 191)->nullable();
            $table->string('actor_role', 16);
            $table->timestampTz('reserved_at');
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampsTz();
            $table->index(['recipient_type', 'recipient_id', 'status']);
        });
    }
};
