<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('tax.tables.tax_zones', 'tax_zones'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();

            // Zone type: country, state, postcode
            $table->string('type')->default('country');

            // Geographic matching
            $table->json('countries')->nullable(); // ['MY', 'SG']
            $table->json('states')->nullable();    // ['Selangor', 'Perak']
            $table->json('postcodes')->nullable(); // ['10000-19999', '50*']

            // Priority for zone matching (higher = checked first)
            $table->integer('priority')->default(0);

            // Flags
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_active', 'priority']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('tax.tables.tax_zones', 'tax_zones'));
    }
};
