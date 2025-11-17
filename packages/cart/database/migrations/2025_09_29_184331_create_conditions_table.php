<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conditions', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Core identification
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();

            // Condition definition
            $table->string('type'); // discount, tax, fee, shipping, etc.
            $table->string('target'); // cart@cart_subtotal/aggregate, etc. (DSL string for UI/filtering)
            $jsonType = (string) commerce_json_column_type('cart', 'json');
            $table->{$jsonType}('target_definition'); // structured scope/phase/application payload
            $table->string('value'); // e.g., "-10%", "+5", "15"

            // Computed fields
            $table->string('operator')->nullable(); // +, -, *, /, %
            $table->boolean('is_charge')->default(false);
            $table->boolean('is_dynamic')->default(false);
            $table->boolean('is_discount')->default(false);
            $table->boolean('is_percentage')->default(false);
            $table->string('parsed_value')->nullable();

            // Configuration
            $table->integer('order')->default(0);
            $table->{$jsonType}('attributes')->nullable();
            $table->{$jsonType}('rules')->nullable();

            // Status
            $table->boolean('is_global')->default(false);
            $table->boolean('is_active')->default(false);

            $table->timestamps();

            // Indexes for filtering and sorting
            $table->index(['type', 'is_active']);
            $table->index(['target', 'is_active']);
            $table->index('is_charge');
            $table->index('is_discount');
            $table->index('is_percentage');
            $table->index('is_dynamic');
            $table->index('is_global');
            $table->index('order');
        });

        if (commerce_json_column_type('cart', 'json') === 'jsonb') {
            Schema::table('conditions', function (Blueprint $table): void {
                DB::statement('CREATE INDEX conditions_attributes_gin_index ON conditions USING GIN (attributes)');
                DB::statement('CREATE INDEX conditions_rules_gin_index ON conditions USING GIN (rules)');
                DB::statement('CREATE INDEX conditions_target_definition_gin_index ON conditions USING GIN (target_definition)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conditions');
    }
};
