<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use Closure;

interface CheckoutStepRegistryInterface
{
    /**
     * Register a checkout step.
     */
    public function register(string $identifier, CheckoutStepInterface $step): void;

    /**
     * Register a checkout step lazily via factory closure.
     * The step will be instantiated on first access, not during registration.
     *
     * @param  Closure(): CheckoutStepInterface  $factory
     */
    public function registerLazy(string $identifier, Closure $factory): void;

    /**
     * Get a step by its identifier.
     */
    public function get(string $identifier): ?CheckoutStepInterface;

    /**
     * Check if a step is registered.
     */
    public function has(string $identifier): bool;

    /**
     * Get all registered steps in execution order.
     *
     * @return array<string, CheckoutStepInterface>
     */
    public function all(): array;

    /**
     * Get steps in configured execution order.
     *
     * @return array<CheckoutStepInterface>
     */
    public function getOrderedSteps(): array;

    /**
     * Set custom step execution order.
     *
     * @param  array<string>  $order  Step identifiers in desired order
     */
    public function setOrder(array $order): void;

    /**
     * Enable a step.
     */
    public function enable(string $identifier): void;

    /**
     * Disable a step.
     */
    public function disable(string $identifier): void;

    /**
     * Check if a step is enabled.
     */
    public function isEnabled(string $identifier): bool;

    /**
     * Replace an existing step with a custom implementation.
     */
    public function replace(string $identifier, CheckoutStepInterface $step): void;

    /**
     * Insert a step before another step.
     */
    public function insertBefore(string $beforeIdentifier, string $identifier, CheckoutStepInterface $step): void;

    /**
     * Insert a step after another step.
     */
    public function insertAfter(string $afterIdentifier, string $identifier, CheckoutStepInterface $step): void;

    /**
     * Get identifiers of enabled steps in order.
     *
     * @return array<string>
     */
    public function getEnabledStepIdentifiers(): array;
}
