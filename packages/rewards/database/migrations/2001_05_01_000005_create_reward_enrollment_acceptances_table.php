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

        Schema::create(config('rewards.database.tables.enrollment_acceptances', 'reward_enrollment_acceptances'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->index();
            $table->unsignedInteger('seq');
            $table->foreignUuid('policy_version_id')->index();
            $table->string('material_terms_hash', 64);
            $table->string('terms_label', 191);
            $table->{$jsonType}('accepted_snapshot');
            $table->timestampTz('accepted_at');
            $table->string('accepted_via', 32)->nullable();
            $table->timestampsTz();

            $table->unique(['enrollment_id', 'seq'], 'reward_acceptances_enrollment_seq_unique');
            $table->index(['enrollment_id', 'accepted_at'], 'reward_acceptances_enrollment_time_idx');
        });
    }
};
