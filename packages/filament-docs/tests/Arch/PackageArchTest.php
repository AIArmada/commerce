<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentDocs')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentDocs');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentDocs')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentDocs\Contracts', 'AIArmada\FilamentDocs\Data'])
    ->not->toUse([
        'AIArmada\FilamentDocs\Actions',
        'AIArmada\FilamentDocs\Models',
        'AIArmada\FilamentDocs\Drivers',
        'AIArmada\FilamentDocs\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
