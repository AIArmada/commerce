<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

interface PaymentGatewayResolverInterface
{
    /**
     * Resolve the appropriate payment processor.
     */
    public function resolve(?string $gateway = null): PaymentProcessorInterface;

    /**
     * Get all available payment processors.
     *
     * @return array<string, PaymentProcessorInterface>
     */
    public function getAvailable(): array;

    /**
     * Check if a specific gateway is available.
     */
    public function hasGateway(string $gateway): bool;

    /**
     * Get the default gateway identifier.
     */
    public function getDefaultGateway(): string;

    /**
     * Register a payment processor.
     */
    public function register(string $identifier, PaymentProcessorInterface $processor): void;
}
