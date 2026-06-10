<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('cart.database.conditions_table', 'conditions');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('deactivated_at')->nullable();
        });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->index('deactivated_at');
        });
    }
};
