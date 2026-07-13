<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('checkout.database.tables.checkout_sessions', 'checkout_sessions');

        if (! Schema::hasColumn($tableName, 'finalization_phase')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('finalization_phase')->nullable()->after('status');
                $table->text('finalization_error')->nullable()->after('finalization_phase');
            });
        }
    }
};
