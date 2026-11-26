<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentAddressing')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentAddressing');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentAddressing')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentAddressing\Contracts', 'AIArmada\FilamentAddressing\Data'])
    ->not->toUse([
        'AIArmada\FilamentAddressing\Actions',
        'AIArmada\FilamentAddressing\Models',
        'AIArmada\FilamentAddressing\Drivers',
        'AIArmada\FilamentAddressing\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
