<?php

declare(strict_types=1);
use AIArmada\Seating\SeatingServiceProvider;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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

arch('seating enums')
    ->expect('AIArmada\Seating\Enums')
    ->toBeEnums();

arch('seating actions')
    ->expect('AIArmada\Seating\Actions')
    ->toHaveMethod('handle');

arch('seating exceptions')
    ->expect('AIArmada\Seating\Exceptions')
    ->toExtend('RuntimeException');

test('seating service provider is concrete', function (): void {
    $provider = new SeatingServiceProvider(app());
    expect($provider)->toBeInstanceOf(PackageServiceProvider::class);
});

test('seating config is accessible', function (): void {
    expect(config('seating.database.tables.seat_maps'))->toBe('seat_maps');
    expect(config('seating.holds.ttl_minutes'))->toBe(15);
});
