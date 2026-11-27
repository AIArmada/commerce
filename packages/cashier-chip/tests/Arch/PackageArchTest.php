<?php

declare(strict_types=1);

arch('strict types across package source')
    ->expect('AIArmada\\CashierChip')
    ->toUseStrictTypes();

arch('package source never imports host application code')
    ->expect('AIArmada\\CashierChip')
    ->not->toUse('App');
