<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->instanceTables() as $tableName) {
            if (! Schema::hasTable($tableName)
                || Schema::hasColumn($tableName, 'owner_type')
                || Schema::hasColumn($tableName, 'owner_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->nullableUuidMorphs('owner');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->instanceTables() as $tableName) {
            if (! Schema::hasTable($tableName)
                || ! Schema::hasColumn($tableName, 'owner_type')
                || ! Schema::hasColumn($tableName, 'owner_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropMorphs('owner');
            });
        }
    }

    /**
     * @return list<string>
     */
    private function instanceTables(): array
    {
        return [
            (string) config('addressing.tables.addresses', 'addresses'),
            (string) config('addressing.tables.addressables', 'addressables'),
            (string) config('addressing.tables.snapshots', 'address_snapshots'),
        ];
    }
};
