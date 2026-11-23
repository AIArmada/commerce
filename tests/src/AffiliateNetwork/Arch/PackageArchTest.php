<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\AffiliateNetwork')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'time_nanosleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\AffiliateNetwork');

arch('package source never imports host application code')
    ->expect('AIArmada\AffiliateNetwork')
    ->not->toUse('App');

arch('public Contracts and Data stay decoupled from Eloquent, Http, Features, and Drivers')
    ->expect(['AIArmada\AffiliateNetwork\Contracts', 'AIArmada\AffiliateNetwork\Data'])
    ->not->toUse([
        'Illuminate\Database\Eloquent',
        'Illuminate\Http',
        'AIArmada\AffiliateNetwork\Features',
        'AIArmada\AffiliateNetwork\Drivers',
    ]);

arch('features never import concrete drivers directly')
    ->expect('AIArmada\AffiliateNetwork\Features')
    ->not->toUse('AIArmada\AffiliateNetwork\Drivers');
