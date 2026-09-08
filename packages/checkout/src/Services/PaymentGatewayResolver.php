<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Services;

use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Exceptions\MissingPaymentGatewayException;
use InvalidArgumentException;

final class PaymentGatewayResolver implements PaymentGatewayResolverInterface
{
    /** @var array<string, PaymentProcessorInterface> */
    private array $processors = [];

    /**
     * Constructor values are test seams for isolated resolver instances. The
     * package service provider constructs this resolver without values so
     * checkout.payment is the single runtime source of truth.
     *
     * @param  array<string>  $priority
     */
    public function __construct(
        private readonly ?string $defaultGateway = null,
        private readonly array $priority = [],
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
        /**
         * Resolution order is explicit gateway, configured default, configured
         * priority, then the first registered processor. The provider passes
         * no defaults, so changing checkout.payment changes this order without
         * relying on a constructor fallback.
         */
        $defaultGateway = $this->defaultGateway ?? config('checkout.payment.default_gateway');

        if (is_string($defaultGateway) && $defaultGateway !== '' && $this->hasGateway($defaultGateway)) {
            return $defaultGateway;
        }

        $priority = $this->priority !== []
            ? $this->priority
            : config('checkout.payment.gateway_priority', []);

        foreach (is_array($priority) ? $priority : [] as $gateway) {
            if (is_string($gateway) && $this->hasGateway($gateway)) {
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
        $identifier = mb_trim($identifier);

        if ($identifier === '') {
            throw new InvalidArgumentException('A checkout payment processor must have a non-empty identifier.');
        }

        if (isset($this->processors[$identifier]) && $this->processors[$identifier] !== $processor) {
            throw new InvalidArgumentException(
                "A different checkout payment processor is already registered for [{$identifier}].",
            );
        }

        $this->processors[$identifier] = $processor;
    }
}
