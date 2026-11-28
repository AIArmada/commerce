<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentEvents')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentEvents');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentEvents')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentEvents\Contracts', 'AIArmada\FilamentEvents\Data'])
    ->not->toUse([
        'AIArmada\FilamentEvents\Actions',
        'AIArmada\FilamentEvents\Models',
        'AIArmada\FilamentEvents\Drivers',
        'AIArmada\FilamentEvents\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
