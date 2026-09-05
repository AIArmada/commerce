<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'ticket_types' => config('ticketing.database.tables.ticket_types', 'ticket_types'),
            'ticket_type_components' => config('ticketing.database.tables.ticket_type_components', 'ticket_type_components'),
            'ticket_type_products' => config('ticketing.database.tables.ticket_type_products', 'ticket_type_products'),
            'ticket_type_seating_options' => config('ticketing.database.tables.ticket_type_seating_options', 'ticket_type_seating_options'),
            'pass_holders' => config('ticketing.database.tables.pass_holders', 'pass_holders'),
            'pass_transfers' => config('ticketing.database.tables.pass_transfers', 'pass_transfers'),
        ];

        foreach ($tables as $key => $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $ownerTypeExists = Schema::hasColumn($tableName, 'owner_type');
            $ownerIdExists = Schema::hasColumn($tableName, 'owner_id');

            if (! $ownerTypeExists && ! $ownerIdExists) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->nullableUuidMorphs('owner');
                });
            } else {
                Schema::table($tableName, function (Blueprint $table) use ($ownerTypeExists, $ownerIdExists): void {
                    if (! $ownerTypeExists) {
                        $table->string('owner_type')->nullable();
                    }

                    if (! $ownerIdExists) {
                        $table->uuid('owner_id')->nullable();
                    }
                });

                $ownerIndex = $key . '_owner_idx';

                if (! Schema::hasIndex($tableName, $ownerIndex)) {
                    Schema::table($tableName, function (Blueprint $table) use ($ownerIndex): void {
                        $table->index(['owner_type', 'owner_id'], $ownerIndex);
                    });
                }
            }
        }
    }
};
