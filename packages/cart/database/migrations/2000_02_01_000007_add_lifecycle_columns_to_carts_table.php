<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('cart.database.table', 'carts');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('checked_out_at')->nullable();
            $table->timestampTz('abandoned_at')->nullable();
            $table->uuid('merged_into_id')->nullable();
        });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->index('expired_at');
            $table->index('checked_out_at');
            $table->index('abandoned_at');
            $table->index('merged_into_id');
        });
    }
};
