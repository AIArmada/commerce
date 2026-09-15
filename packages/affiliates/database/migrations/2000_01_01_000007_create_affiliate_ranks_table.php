<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.ranks', 'affiliate_ranks');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->integer('level');
            $table->integer('min_personal_sales')->default(0);
            $table->integer('min_team_sales')->default(0);
            $table->integer('min_active_downlines')->default(0);
            $table->integer('commission_rate_basis_points');

            $table->nullableUuidMorphs('owner');

            $jsonType = commerce_json_column_type('affiliates', 'jsonb');
            $table->addColumn($jsonType, 'override_rates')->nullable();
            $table->addColumn($jsonType, 'benefits')->nullable();
            $table->addColumn($jsonType, 'metadata')->nullable();

            $table->timestampsTz();

            // Ranks are per-owner ladders: slugs and levels are unique within
            // an owner scope, not globally.
            $table->unique(['owner_type', 'owner_id', 'slug'], 'affiliate_ranks_owner_slug_unique');
            $table->unique(['owner_type', 'owner_id', 'level'], 'affiliate_ranks_owner_level_unique');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.ranks', 'affiliate_ranks');
        Schema::dropIfExists($tableName);
    }
};
