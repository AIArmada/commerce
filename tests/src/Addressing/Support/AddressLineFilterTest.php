<?php

declare(strict_types=1);

use AIArmada\Addressing\Support\AddressLineFilter;

it('drops only null and blank lines while keeping zero strings', function (): void {
    expect(AddressLineFilter::present(['0', null, '', 'Main St']))->toBe(['0', 3 => 'Main St']);
});
