<?php

declare(strict_types=1);

it('publishes invoice company detail settings', function (): void {
    $config = require dirname(__DIR__, 3) . '/packages/orders/config/orders.php';

    expect($config['company'] ?? null)
        ->toBeArray()
        ->toHaveKeys(['address', 'phone', 'email']);
});
