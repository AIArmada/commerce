<?php

declare(strict_types=1);

arch('seating')
    ->expect('AIArmada\Seating')
    ->not->toUse('AIArmada\Events')
    ->not->toUse('AIArmada\Ticketing');

arch('seating contracts')
    ->expect('AIArmada\Seating\Contracts')
    ->toBeInterface();

arch('seating data')
    ->expect('AIArmada\Seating\Data')
    ->toExtend('Spatie\LaravelData\Data');

arch('seating allocators')
    ->expect('AIArmada\Seating\Services\DefaultSeatAllocator')
    ->toImplement('AIArmada\Seating\Contracts\SeatAllocatorInterface');

arch('seating null allocator')
    ->expect('AIArmada\Seating\Services\NullSeatAllocator')
    ->toImplement('AIArmada\Seating\Contracts\SeatAllocatorInterface');

arch('seating no filament')
    ->expect('AIArmada\Seating\Services')
    ->not->toUse('Filament');

test('seating service provider is concrete', function (): void {
    $provider = new \AIArmada\Seating\SeatingServiceProvider(app());
    expect($provider)->toBeInstanceOf(\Spatie\LaravelPackageTools\PackageServiceProvider::class);
});

test('seating config is accessible', function (): void {
    expect(config('seating.database.tables.seat_maps'))->toBe('seat_maps');
    expect(config('seating.holds.ttl_minutes'))->toBe(15);
});
