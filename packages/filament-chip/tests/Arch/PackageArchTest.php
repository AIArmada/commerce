<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentChip')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentChip');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentChip')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentChip\Contracts', 'AIArmada\FilamentChip\Data'])
    ->not->toUse([
        'AIArmada\FilamentChip\Actions',
        'AIArmada\FilamentChip\Models',
        'AIArmada\FilamentChip\Drivers',
        'AIArmada\FilamentChip\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
