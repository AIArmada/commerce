<?php

declare(strict_types=1);

arch('filament growth source uses strict types and no host application imports')
    ->expect('AIArmada\\FilamentGrowth')
    ->toUseStrictTypes()
    ->not->toUse('App');

arch('the domain package never imports its Filament adapter')
    ->expect('AIArmada\\Growth')
    ->not->toUse('AIArmada\\FilamentGrowth');
