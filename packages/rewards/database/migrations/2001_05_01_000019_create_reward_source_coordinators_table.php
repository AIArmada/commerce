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

        Schema::create(config('rewards.database.tables.source_coordinators', 'reward_source_coordinators'), function (Blueprint $table): void {

            $table->uuid('id')->primary();
            $table->string('source_business_key', 191);
            $table->string('component', 16);
            $table->string('beneficiary_role', 32);
            $table->timestampsTz();

            $table->unique(
                ['source_business_key', 'component', 'beneficiary_role'],
                'reward_coordinators_source_component_role_unique'
            );
        });
    }
};
