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

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $hasFinalizationPhase = Schema::hasColumn($tableName, 'finalization_phase');
        $hasFinalizationError = Schema::hasColumn($tableName, 'finalization_error');

        if (! $hasFinalizationPhase || ! $hasFinalizationError) {
            Schema::table($tableName, function (Blueprint $table) use ($hasFinalizationPhase, $hasFinalizationError): void {
                if (! $hasFinalizationPhase) {
                    $table->string('finalization_phase')->nullable()->after('status');
                }

                if (! $hasFinalizationError) {
                    $table->text('finalization_error')->nullable()->after('finalization_phase');
                }
            });
        }
    }
};
