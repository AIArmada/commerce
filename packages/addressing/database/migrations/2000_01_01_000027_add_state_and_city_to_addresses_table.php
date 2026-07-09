<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('addressing.tables.addresses', 'addresses');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->foreignUuid('state_id')->nullable()->index();
            $table->foreignUuid('city_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        $tableName = config('addressing.tables.addresses', 'addresses');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['state_id']);
            $table->dropColumn('state_id');
            $table->dropIndex(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
