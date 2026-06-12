<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Facades;

use Illuminate\Support\Facades\Facade;

final class Engagement extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'engagement';
    }
}
