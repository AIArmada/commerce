<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Facades;

use AIArmada\Cashier\GatewayManager;
use Illuminate\Support\Facades\Facade;

/**
 * Cashier Facade for accessing the GatewayManager.
 *
 * @method static \AIArmada\Cashier\Contracts\GatewayContract gateway(?string $name = null)
 * @method static string getDefaultDriver()
 * @method static array<string> supportedGateways()
 * @method static bool supportsGateway(string $name)
 * @method static array<string, mixed> getGatewayConfig(string $name)
 * @method static static extend(string $driver, \Closure $callback)
 *
 * @see GatewayManager
 */
class Cashier extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return GatewayManager::class;
    }
}
