<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Support\Facades\DB;

it('resolves the current database driver name from a concrete connection', function (): void {
    expect(ConnectionDriver::name(DB::connection()))->toBe('sqlite');
});
