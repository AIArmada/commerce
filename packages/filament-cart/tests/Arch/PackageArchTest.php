<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentCart')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentCart');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentCart')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentCart\Contracts', 'AIArmada\FilamentCart\Data'])
    ->not->toUse([
        'AIArmada\FilamentCart\Actions',
        'AIArmada\FilamentCart\Models',
        'AIArmada\FilamentCart\Drivers',
        'AIArmada\FilamentCart\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
