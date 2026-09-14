<?php

declare(strict_types=1);

use AIArmada\FilamentInventory\Actions\AdjustStockAction;
use AIArmada\FilamentInventory\Actions\CycleCountAction;
use AIArmada\FilamentInventory\Actions\ReceiveStockAction;
use AIArmada\FilamentInventory\Actions\ShipStockAction;
use AIArmada\FilamentInventory\Actions\TransferStockAction;
use AIArmada\Inventory\Models\InventoryLocation;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Livewire\Component as LivewireComponent;

if (! function_exists('locationSelect_makeSchemaLivewire')) {
    function locationSelect_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            use InteractsWithSchemas;

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }
        };
    }
}

if (! function_exists('locationSelect_findSelect')) {
    function locationSelect_findSelect(Action $action, string $name): ?Select
    {
        $schema = $action->getSchema(Schema::make(locationSelect_makeSchemaLivewire()));

        if ($schema === null) {
            return null;
        }

        $walk = function (array $components) use (&$walk, $name): ?Select {
            foreach ($components as $component) {
                if ($component instanceof Select && $component->getName() === $name) {
                    return $component;
                }

                if ($component instanceof Component && method_exists($component, 'getChildComponents')) {
                    $found = $walk($component->getChildComponents());

                    if ($found !== null) {
                        return $found;
                    }
                }
            }

            return null;
        };

        return $walk($schema->getComponents());
    }
}

beforeEach(function (): void {
    config()->set('inventory.owner.enabled', false);
});

it('searches locations lazily instead of preloading whole-table options', function (): void {
    InventoryLocation::factory()->create(['name' => 'Alpha Warehouse']);
    InventoryLocation::factory()->create(['name' => 'Beta Warehouse']);

    $selects = [
        locationSelect_findSelect(ShipStockAction::make(), 'location_id'),
        locationSelect_findSelect(AdjustStockAction::make(), 'location_id'),
        locationSelect_findSelect(ReceiveStockAction::make(), 'location_id'),
        locationSelect_findSelect(CycleCountAction::make(), 'location_id'),
        locationSelect_findSelect(TransferStockAction::make(), 'from_location_id'),
        locationSelect_findSelect(TransferStockAction::make(), 'to_location_id'),
    ];

    foreach ($selects as $select) {
        expect($select)->toBeInstanceOf(Select::class)
            ->and($select->isPreloaded())->toBeFalse();
    }

    $results = $selects[0]->getSearchResults('Alpha');

    expect($results)->toHaveCount(1)
        ->and(array_values($results)[0])->toBe('Alpha Warehouse');
});
