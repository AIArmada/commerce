<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentLinks')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentLinks');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentLinks')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentLinks\Contracts', 'AIArmada\FilamentLinks\Data'])
    ->not->toUse([
        'AIArmada\FilamentLinks\Actions',
        'AIArmada\FilamentLinks\Models',
        'AIArmada\FilamentLinks\Drivers',
        'AIArmada\FilamentLinks\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
