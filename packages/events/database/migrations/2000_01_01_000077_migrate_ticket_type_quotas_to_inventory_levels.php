<?php

declare(strict_types=1);

use AIArmada\Events\Models\EventTicketType;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(InventoryLevel::class) || ! class_exists(InventoryLocation::class)) {
            return;
        }

        $levelsTable = config('inventory.database.tables.levels', 'inventory_levels');
        $locationsTable = config('inventory.database.tables.locations', 'inventory_locations');

        if (! Schema::hasTable($levelsTable) || ! Schema::hasTable($locationsTable)) {
            return;
        }

        if (! (bool) config('events.features.inventory.auto_register_quotas_on_migrate', true)) {
            return;
        }

        $defaultLocation = $this->resolveDefaultLocation();

        if ($defaultLocation === null) {
            return;
        }

        $tableName = config('events.database.tables.event_ticket_types', 'event_ticket_types');

        if (! Schema::hasColumn($tableName, 'quota')) {
            return;
        }

        EventTicketType::query()
            ->whereNotNull('quota')
            ->where('quota', '>', 0)
            ->each(function (EventTicketType $ticketType) use ($defaultLocation): void {
                $exists = InventoryLevel::query()
                    ->where('inventoryable_type', $ticketType->getMorphClass())
                    ->where('inventoryable_id', $ticketType->getKey())
                    ->where('location_id', $defaultLocation->getKey())
                    ->exists();

                if ($exists) {
                    return;
                }

                InventoryLevel::create([
                    'inventoryable_type' => $ticketType->getMorphClass(),
                    'inventoryable_id' => $ticketType->getKey(),
                    'location_id' => $defaultLocation->getKey(),
                    'quantity_on_hand' => (int) $ticketType->quota,
                    'quantity_reserved' => 0,
                    'unit_of_measure' => 'each',
                    'unit_conversion_factor' => 1.0,
                ]);
            });
    }

    private function resolveDefaultLocation(): ?InventoryLocation
    {
        $locationId = config('events.features.inventory.default_location_id', 'default');

        $location = InventoryLocation::query()
            ->where('code', $locationId)
            ->orWhere('id', $locationId)
            ->first();

        if ($location !== null) {
            return $location;
        }

        return InventoryLocation::query()->first();
    }
};
