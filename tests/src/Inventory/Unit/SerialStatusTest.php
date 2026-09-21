<?php

declare(strict_types=1);

use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\States\Disposed;
use AIArmada\Inventory\States\Lost;
use AIArmada\Inventory\States\Reserved;
use AIArmada\Inventory\States\Returned;
use AIArmada\Inventory\States\SerialStatus;
use AIArmada\Inventory\States\Shipped;
use AIArmada\Inventory\States\Sold;
use Illuminate\Support\Facades\DB;

test('every former enum value round-trips through the serial state cast', function (): void {
    $formerEnumValues = [
        'available',
        'reserved',
        'sold',
        'shipped',
        'returned',
        'in_repair',
        'disposed',
        'lost',
        'recalled',
    ];

    $stateClasses = SerialStatus::classes();
    $morphValues = array_map(
        static fn (string $stateClass): string => $stateClass::getMorphClass(),
        $stateClasses,
    );

    expect($morphValues)->toEqualCanonicalizing($formerEnumValues);

    foreach ($stateClasses as $stateClass) {
        $serial = InventorySerial::factory()->create([
            'status' => $stateClass::getMorphClass(),
        ]);
        $reloaded = $serial->fresh();

        expect($reloaded)->toBeInstanceOf(InventorySerial::class);

        if (! $reloaded instanceof InventorySerial) {
            continue;
        }

        expect($reloaded->status)->toBeInstanceOf($stateClass);
        expect($reloaded->status->getValue())->toBe($stateClass::getMorphClass());
    }
});

test('the serial status audit query reports no unknown morph values on clean data', function (): void {
    $knownMorphValues = array_map(
        static fn (string $stateClass): string => $stateClass::getMorphClass(),
        SerialStatus::classes(),
    );

    $unknownCount = DB::table(config('inventory.database.tables.serials', 'inventory_serials'))
        ->whereNotIn('status', $knownMorphValues)
        ->count();

    expect($unknownCount)->toBe(0);
});

test('SerialStatus canTransitionTo works correctly', function (): void {
    $available = new InventorySerial(['status' => Available::class]);
    $sold = new InventorySerial(['status' => Sold::class]);
    $disposed = new InventorySerial(['status' => Disposed::class]);
    $lost = new InventorySerial(['status' => Lost::class]);

    expect($available->status->canTransitionTo(Reserved::class))->toBeTrue();
    expect($available->status->canTransitionTo(Disposed::class))->toBeTrue();
    expect($sold->status->canTransitionTo(Available::class))->toBeFalse();
    expect($disposed->status->canTransitionTo(Available::class))->toBeFalse();
    expect($sold->status->canTransitionTo(Shipped::class))->toBeTrue();
    expect($sold->status->canTransitionTo(Returned::class))->toBeTrue();
    expect($lost->status->canTransitionTo(Available::class))->toBeTrue();
});
