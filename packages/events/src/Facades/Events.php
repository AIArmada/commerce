<?php

declare(strict_types=1);

namespace AIArmada\Events\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Events\Services\EventQueryService query()
 * @method static \AIArmada\Events\Services\RegistrationService registration()
 *
 * @see \AIArmada\Events\EventsServiceProvider
 */
final class Events extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'events';
    }
}
