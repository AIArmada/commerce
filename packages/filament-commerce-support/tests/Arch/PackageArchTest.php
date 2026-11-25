<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentCommerceSupport')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentCommerceSupport');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentCommerceSupport')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentCommerceSupport\Contracts', 'AIArmada\FilamentCommerceSupport\Data'])
    ->not->toUse([
        'AIArmada\FilamentCommerceSupport\Actions',
        'AIArmada\FilamentCommerceSupport\Models',
        'AIArmada\FilamentCommerceSupport\Drivers',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
