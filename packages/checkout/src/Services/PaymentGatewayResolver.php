<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Services;

use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Exceptions\MissingPaymentGatewayException;

final class PaymentGatewayResolver implements PaymentGatewayResolverInterface
{
    /** @var array<string, PaymentProcessorInterface> */
    private array $processors = [];

    /**
     * @param  array<string>  $priority
     */
    public function __construct(
        private readonly ?string $defaultGateway,
        private readonly array $priority = ['cashier', 'cashier-chip', 'chip'],
    ) {}

    public function resolve(?string $gateway = null): PaymentProcessorInterface
    {
        $identifier = $gateway ?? $this->getDefaultGateway();

        if (! isset($this->processors[$identifier])) {
            throw MissingPaymentGatewayException::gatewayNotFound($identifier);
        }

        return $this->processors[$identifier];
    }

    /**
     * @return array<string, PaymentProcessorInterface>
     */
    public function getAvailable(): array
    {
        return $this->processors;
    }

    public function hasGateway(string $gateway): bool
    {
        return isset($this->processors[$gateway]);
    }

    public function getDefaultGateway(): string
    {
        // Use configured default if available
        if ($this->defaultGateway !== null && $this->hasGateway($this->defaultGateway)) {
            return $this->defaultGateway;
        }

        // Fall back to priority order
        foreach ($this->priority as $gateway) {
            if ($this->hasGateway($gateway)) {
                return $gateway;
            }
        }

        // Fall back to first available
        $available = array_keys($this->processors);
        if (empty($available)) {
            throw MissingPaymentGatewayException::noGatewayInstalled();
        }

        return $available[0];
    }

    public function register(string $identifier, PaymentProcessorInterface $processor): void
    {
        $this->processors[$identifier] = $processor;
    }
}
