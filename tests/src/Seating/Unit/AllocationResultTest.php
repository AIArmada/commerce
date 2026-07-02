<?php

declare(strict_types=1);

use AIArmada\Seating\Data\AllocationResult;

it('can be created with required data', function () {
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

it('can be created with category', function () {
    $result = new AllocationResult(
        seatId: 'uuid-1',
        sectionCode: 'B',
        rowLabel: '2',
        seatLabel: '10',
        category: 'vip',
    );

    expect($result->category)->toBe('vip');
});

it('has expected properties', function () {
    $result = new AllocationResult(
        seatId: 'uuid-1',
        sectionCode: 'C',
        rowLabel: '3',
        seatLabel: '15',
        category: 'standard',
    );

    expect($result->seatId)->toBe('uuid-1');
    expect($result->sectionCode)->toBe('C');
    expect($result->category)->toBe('standard');
});
