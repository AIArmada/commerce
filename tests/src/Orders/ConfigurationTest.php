<?php

declare(strict_types=1);

it('publishes invoice company detail settings', function (): void {
    $config = require __DIR__ . '/../../../packages/orders/config/orders.php';

    expect($config['company'] ?? null)
        ->toBeArray()
        ->toHaveKeys(['address', 'phone', 'email']);
});
