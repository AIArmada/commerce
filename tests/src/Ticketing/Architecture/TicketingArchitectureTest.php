<?php

declare(strict_types=1);

arch('ticketing')
    ->expect('AIArmada\Ticketing')
    ->not->toUse('AIArmada\Events');
