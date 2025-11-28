<?php

declare(strict_types=1);

namespace AIArmada\Cashier;

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Exceptions\GatewayNotFoundException;
use Illuminate\Support\Manager;

/**
 * Gateway Manager - Factory pattern for resolving payment gateways.
 *
 * This class extends Laravel's Manager pattern to provide a consistent
 * interface for resolving and creating gateway instances.
 */
class GatewayManager extends Manager
{
    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array<mixed>  $parameters
     */
    public function __call($method, $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('cashier.default', 'stripe');
    }

    /**
     * Get a gateway instance.
     *
     *
     * @throws GatewayNotFoundException
     */
    public function gateway(?string $name = null): GatewayContract
    {
        return $this->driver($name);
    }

    /**
     * Get all supported gateways.
     *
     * @return array<string>
     */
    public function supportedGateways(): array
    {
        return array_keys($this->config->get('cashier.gateways', []));
    }

    /**
     * Determine if a gateway is supported.
     */
    public function supportsGateway(string $name): bool
    {
        return in_array($name, $this->supportedGateways());
    }

    /**
     * Get the gateway configuration.
     *
     * @return array<string, mixed>
     */
    public function getGatewayConfig(string $name): array
    {
        return $this->config->get("cashier.gateways.{$name}", []);
    }

    /**
     * Create a Stripe gateway driver.
     */
    protected function createStripeDriver(): GatewayContract
    {
        $config = $this->config->get('cashier.gateways.stripe', []);

        return $this->buildGateway('stripe', Gateways\StripeGateway::class, $config);
    }

    /**
     * Create a CHIP gateway driver.
     */
    protected function createChipDriver(): GatewayContract
    {
        $config = $this->config->get('cashier.gateways.chip', []);

        return $this->buildGateway('chip', Gateways\ChipGateway::class, $config);
    }

    /**
     * Build a gateway instance.
     *
     * @param  class-string<GatewayContract>  $class
     * @param  array<string, mixed>  $config
     */
    protected function buildGateway(string $name, string $class, array $config): GatewayContract
    {
        if (! class_exists($class)) {
            throw new GatewayNotFoundException("Gateway class [{$class}] not found. Make sure the required package is installed.");
        }

        return new $class($config);
    }
}
