<?php

declare(strict_types=1);

arch('filament growth source uses strict types')
    ->expect('AIArmada\\FilamentGrowth')
    ->toUseStrictTypes();

arch('filament growth source never imports host application code')
    ->expect('AIArmada\\FilamentGrowth')
    ->not->toUse('App');

arch('the domain package never imports its Filament adapter')
    ->expect('AIArmada\\Growth')
    ->not->toUse('AIArmada\\FilamentGrowth');
