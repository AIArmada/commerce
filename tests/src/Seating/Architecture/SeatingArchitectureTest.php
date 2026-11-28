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

arch('seating enums')
    ->expect('AIArmada\Seating\Enums')
    ->toBeEnums();

arch('seating map actions')
    ->expect('AIArmada\Seating\Features\Maps\Actions')
    ->toHaveMethod('handle');

arch('seating exceptions')
    ->expect('AIArmada\Seating\Exceptions')
    ->toExtend('RuntimeException');

arch('seating reservation actions')
    ->expect('AIArmada\Seating\Features\Reservations\Actions')
    ->toHaveMethod('handle');
