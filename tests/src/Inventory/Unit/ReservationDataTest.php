<?php

declare(strict_types=1);

use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Data\ReservationOutcome;

it('keeps reservation request and result snapshots immutable', function (): void {
    $line = new ReservationLine(productId: 'product-1', quantity: 3);
    $outcome = new ReservationOutcome(reference: 'reference-1', state: 'reserved');

    expect(fn () => $line->quantity = 1)->toThrow(Error::class)
        ->and(fn () => $outcome->state = 'committed')->toThrow(Error::class);
});
