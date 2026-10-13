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

        Schema::create(config('rewards.database.tables.funding_entries', 'reward_funding_entries'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->index();
            $table->char('currency', 3)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('source_ref', 191);
            $table->foreignUuid('source_id')->index();
            $table->{$jsonType}('evidence')->nullable();
            $table->string('funded_by_type', 32);
            $table->string('funded_by_id', 191)->nullable();
            $table->string('funded_by_role', 16);
            $table->timestampTz('funded_at');
            $table->timestampsTz();
            $table->unique(['program_id', 'source_ref']);
        });
    }
};
