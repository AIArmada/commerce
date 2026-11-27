<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentReferrals')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentReferrals');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentReferrals')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Actions, Models, and Drivers')
    ->expect(['AIArmada\FilamentReferrals\Contracts', 'AIArmada\FilamentReferrals\Data'])
    ->not->toUse([
        'AIArmada\FilamentReferrals\Actions',
        'AIArmada\FilamentReferrals\Models',
        'AIArmada\FilamentReferrals\Drivers',
        'AIArmada\FilamentReferrals\Features',
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
    ]);
