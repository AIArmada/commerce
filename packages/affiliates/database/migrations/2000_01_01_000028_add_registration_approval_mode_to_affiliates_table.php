<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.affiliates', 'affiliates');

        if (! Schema::hasColumn($tableName, 'registration_approval_mode')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('registration_approval_mode', 16)->default('admin');
            });
        }

        if (! Schema::hasIndex($tableName, 'affiliates_open_approval_idx')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['registration_approval_mode', 'status'], 'affiliates_open_approval_idx');
            });
        }
    }
};
