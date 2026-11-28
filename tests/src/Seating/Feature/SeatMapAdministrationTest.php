<?php

declare(strict_types=1);

use AIArmada\Seating\Features\Maps\Actions\SaveSeatMap;
use AIArmada\Seating\Features\Maps\Actions\DeleteSeatMap;
use AIArmada\Seating\Features\Maps\Models\SeatMap;
use Illuminate\Validation\ValidationException;

it('persists structured layout metadata without double encoding', function (): void {
    $map = app(SaveSeatMap::class)->handle([
        'name' => 'Main hall',
        'slug' => 'main-hall',
        'status' => 'active',
        'layout_metadata' => ['rows' => 3],
    ]);

    expect($map->fresh()->layout_metadata)->toBe(['rows' => 3]);

    app(SaveSeatMap::class)->handle([
        'name' => 'Updated hall',
        'slug' => 'main-hall',
        'status' => 'inactive',
    ], $map);

    expect($map->fresh()->name)->toBe('Updated hall')
        ->and($map->fresh()->layout_metadata)->toBe(['rows' => 3]);

    expect(app(DeleteSeatMap::class)->handle($map))->toBeTrue()
        ->and(SeatMap::query()->whereKey($map->getKey())->exists())->toBeFalse();
});

it('rejects scalar layout metadata', function (): void {
    expect(fn () => app(SaveSeatMap::class)->handle([
        'name' => 'Invalid hall',
        'slug' => 'invalid-hall',
        'status' => 'active',
        'layout_metadata' => 'not an array',
    ]))->toThrow(ValidationException::class);
});
