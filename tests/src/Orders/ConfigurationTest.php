<?php

declare(strict_types=1);

it('publishes invoice company detail settings', function (): void {
    expect(config('orders.company'))
        ->toBeArray()
        ->toHaveKeys(['address', 'phone', 'email']);
});
