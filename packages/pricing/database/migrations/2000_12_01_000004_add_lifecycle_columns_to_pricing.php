<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('pricing.database.tables.price_lists', 'price_lists'), function (Blueprint $table): void {
            $table->timestampTz('deactivated_at')->nullable()->after('is_active');
        });

        Schema::table(config('pricing.database.tables.prices', 'prices'), function (Blueprint $table): void {
            $table->timestampTz('deactivated_at')->nullable()->after('min_quantity');
        });

        Schema::table(config('pricing.database.tables.price_tiers', 'price_tiers'), function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('discount_value');
        });
    }
};
