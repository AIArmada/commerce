<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config(
            'cashier-chip.database.tables.renewal_attempts',
            'cashier_chip_renewal_attempts',
        );

        if (! Schema::hasTable($tableName)) {
            return;
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
        }

        if (! Schema::hasIndex($tableName, ['owner_type', 'owner_id'])) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['owner_type', 'owner_id']);
            });
        }
    }
};
