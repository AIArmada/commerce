<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\FilamentOrganizations')
    ->toUseStrictTypes();

arch('no debugging helpers, env(), or imperative sleep in package source')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep', 'env'])
    ->each->not->toBeUsedIn('AIArmada\FilamentOrganizations');

arch('package source never imports host application code')
    ->expect('AIArmada\FilamentOrganizations')
    ->not->toUse('App');
