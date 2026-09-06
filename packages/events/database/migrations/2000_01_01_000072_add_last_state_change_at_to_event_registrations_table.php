<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('events.database.tables.event_registrations', 'event_registrations');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'last_state_change_at')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestampTz('last_state_change_at')->nullable()->index();
            });
        }

        foreach ([
            'confirmed' => 'approved_at',
            'completed' => 'completed_at',
            'cancelled' => 'cancelled_at',
            'rejected' => 'rejected_at',
            'waitlisted' => 'waitlisted_at',
            'refund_pending' => 'refund_pending_at',
            'refunded' => 'refunded_at',
            'expired' => 'expired_at',
        ] as $status => $timestampColumn) {
            DB::table($tableName)
                ->whereNull('last_state_change_at')
                ->where('status', $status)
                ->update([
                    'last_state_change_at' => DB::raw("COALESCE({$timestampColumn}, registered_at, created_at)"),
                ]);
        }

        DB::table($tableName)
            ->whereNull('last_state_change_at')
            ->update([
                'last_state_change_at' => DB::raw('COALESCE(registered_at, created_at)'),
            ]);
    }
};
