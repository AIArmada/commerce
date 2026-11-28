<?php

declare(strict_types=1);

arch('strict types across Vouchers package source')
    ->expect('AIArmada\Vouchers')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in Vouchers package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'time_nanosleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\Vouchers');

arch('Vouchers package source never imports host application code')
    ->expect('AIArmada\Vouchers')
    ->not->toUse('App');

arch('voucher data and feature APIs remain framework-independent')
    ->expect([
        'AIArmada\Vouchers\Data',
        'AIArmada\Vouchers\Features\CartIntegration\Api',
        'AIArmada\Vouchers\Features\Lifecycle\Api',
    ])
    ->not->toUse([
        'AIArmada\Vouchers\Features\Lifecycle\Models',
        'AIArmada\Vouchers\Features\Lifecycle\Actions',
        'AIArmada\Vouchers\Features\Wallet\Actions',
        'AIArmada\Vouchers\Features\CartIntegration\Actions',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);

arch('voucher platform never depends on feature implementations')
    ->expect('AIArmada\Vouchers\Platform')
    ->not->toUse('AIArmada\Vouchers\Features');

arch('cart integration does not import voucher persistence models')
    ->expect('AIArmada\Vouchers\Features\CartIntegration')
    ->not->toUse('AIArmada\Vouchers\Features\Lifecycle\Models');
