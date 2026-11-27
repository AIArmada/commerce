<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\\Chip')
    ->toUseStrictTypes();

arch('package source never imports host application code')
    ->expect('AIArmada\\Chip')
    ->not->toUse('App');
