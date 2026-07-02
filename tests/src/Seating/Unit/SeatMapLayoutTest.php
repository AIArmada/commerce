<?php

declare(strict_types=1);

use AIArmada\Seating\Data\SeatMapLayout;

it('can be created with defaults', function (): void {
    $layout = new SeatMapLayout(
        id: 'uuid-1',
        name: 'Main Hall',
        slug: 'main-hall',
        version: 1,
    );

    expect($layout->id)->toBe('uuid-1');
    expect($layout->sections)->toBe([]);
    expect($layout->bounds)->toBe(['rows' => 0, 'cols' => 0]);
});

it('can be created with sections and bounds', function (): void {
    $layout = new SeatMapLayout(
        id: 'uuid-2',
        name: 'VIP Room',
        slug: 'vip-room',
        version: 2,
        sections: [['code' => 'A', 'name' => 'Section A']],
        bounds: ['rows' => 5, 'cols' => 10],
    );

    expect($layout->sections)->toHaveCount(1);
    expect($layout->bounds['rows'])->toBe(5);
});

it('has expected properties', function (): void {
    $layout = new SeatMapLayout(
        id: 'uuid-3',
        name: 'Test',
        slug: 'test',
        version: 1,
    );

    expect($layout->id)->toBe('uuid-3');
    expect($layout->name)->toBe('Test');
    expect($layout->version)->toBe(1);
});
