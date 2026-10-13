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

        Schema::create(config('rewards.database.tables.point_lots', 'reward_point_lots'), function (Blueprint $table): void {

            $table->uuid('id')->primary();
            $table->string('recipient_type', 64)->index();
            $table->foreignUuid('recipient_id')->index();
            $table->string('family_key', 64)->index();
            $table->foreignUuid('program_id')->nullable()->index();
            $table->foreignUuid('award_id')->nullable()->index();
            $table->unsignedInteger('seq');
            $table->string('unit', 16)->index();
            $table->unsignedBigInteger('credited_points');
            $table->string('source_operation_key', 191)->unique();
            $table->timestampTz('available_at');
            $table->timestampTz('expires_at')->nullable()->index();
            $table->string('status', 16)->index();
            $table->timestampTz('credited_at');
            $table->timestampTz('depleted_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampsTz();

            $table->index(
                ['recipient_type', 'recipient_id', 'family_key', 'unit', 'status'],
                'reward_lots_recipient_family_unit_status_idx'
            );
            $table->index(
                ['recipient_type', 'recipient_id', 'family_key', 'unit', 'expires_at'],
                'reward_lots_recipient_expiry_idx'
            );
        });
    }
};
