<?php

declare(strict_types=1);

use AIArmada\Seating\Data\AllocationResult;

it('can be created with required data', function (): void {
    $result = new AllocationResult(
        seatId: 'uuid-1',
        sectionCode: 'A',
        rowLabel: '1',
        seatLabel: '5',
    );

    expect($result->seatId)->toBe('uuid-1');
    expect($result->sectionCode)->toBe('A');
    expect($result->rowLabel)->toBe('1');
    expect($result->seatLabel)->toBe('5');
});

it('can be created with category', function (): void {
    $result = new AllocationResult(
        seatId: 'uuid-1',
        sectionCode: 'B',
        rowLabel: '2',
        seatLabel: '10',
        category: 'vip',
    );

    expect($result->category)->toBe('vip');
});

/* Property-readback subset removed; covered by the two creation tests above. */
