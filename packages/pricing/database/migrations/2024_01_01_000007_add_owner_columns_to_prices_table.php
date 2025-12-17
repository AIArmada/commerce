<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table((string) config('pricing.database.tables.prices', 'prices'), function (Blueprint $table): void {
            $table->nullableMorphs('owner');
        });
    }

    public function down(): void
    {
        Schema::table((string) config('pricing.database.tables.prices', 'prices'), function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });
    }
};
