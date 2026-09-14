<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Actions;

use AIArmada\Shipping\Actions\RecalculateShipmentWeight;
use AIArmada\Shipping\Models\Shipment;
use Illuminate\Support\Facades\DB;

function weightedShipment(): Shipment
{
    $shipment = Shipment::query()->create([
        'reference' => 'SHP-WEIGHT',
        'carrier_code' => 'manual',
        'origin_address' => ['name' => 'Origin'],
        'destination_address' => ['name' => 'Dest'],
    ]);

    $shipment->items()->create(['name' => 'Heavy', 'quantity' => 2, 'weight' => 100]);
    $shipment->items()->create(['name' => 'Light', 'quantity' => 3, 'weight' => 50]);

    return $shipment;
}

describe('Shipment weight recalculation', function (): void {
    it('sums weight times quantity with a single aggregate query', function (): void {
        $shipment = weightedShipment();

        $aggregateSelects = 0;
        $hydratingSelects = 0;

        DB::listen(function ($query) use (&$aggregateSelects, &$hydratingSelects): void {
            $sql = mb_ltrim($query->sql);

            if (! str_starts_with($sql, 'select')) {
                return;
            }

            if (str_contains($query->sql, 'weight * quantity')) {
                $aggregateSelects++;
            } elseif (str_contains($query->sql, 'shipment_items')) {
                $hydratingSelects++;
            }
        });

        $recalculated = RecalculateShipmentWeight::run($shipment);

        expect($recalculated->total_weight)->toBe(350)
            ->and($aggregateSelects)->toBe(1)
            ->and($hydratingSelects)->toBe(0);
    });
});
