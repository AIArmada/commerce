<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $occurrencesTable = (string) config('events.database.tables.occurrences', 'event_occurrences');
        $subLocationsTable = (string) config('events.database.tables.sub_locations', 'event_sub_locations');

        $this->upgradeOccurrencesTable($occurrencesTable);
        $this->createSubLocationsTable($subLocationsTable);
    }

    private function upgradeOccurrencesTable(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $hasAddressType = Schema::hasColumn($tableName, 'address_type');
            $hasAddressId = Schema::hasColumn($tableName, 'address_id');

            if (! $hasAddressType && ! $hasAddressId) {
                $table->nullableUuidMorphs('address');
            } else {
                if (! $hasAddressType) {
                    $table->string('address_type')->nullable()->after('event_id');
                }

                if (! $hasAddressId) {
                    $table->uuid('address_id')->nullable()->after('address_type');
                }
            }

            if (! Schema::hasColumn($tableName, 'sub_location_id')) {
                $table->foreignUuid('sub_location_id')->nullable()->index()->after('address_id');
            }
        });
    }

    private function createSubLocationsTable(string $tableName): void
    {
        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'description')) {
                    $table->text('description')->nullable()->after('slug');
                }

                if (! Schema::hasColumn($tableName, 'order_column')) {
                    $table->unsignedInteger('order_column')->nullable()->after('description');
                }
            });

            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'slug']);
            $table->index(['owner_type', 'owner_id', 'order_column']);
        });
    }
};
