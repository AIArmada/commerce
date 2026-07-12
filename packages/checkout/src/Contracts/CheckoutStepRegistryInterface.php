<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

interface CheckoutStepRegistryInterface
{
    public function get(string $identifier): ?CheckoutStepInterface;

    public function has(string $identifier): bool;

    /** @return array<string, CheckoutStepInterface> */
    public function all(): array;

    /** @return array<CheckoutStepInterface> */
    public function getOrderedSteps(): array;

    /** @return array<string> */
    public function getOrder(): array;

    public function isEnabled(string $identifier): bool;

    /** @return array<string> */
    public function getEnabledStepIdentifiers(): array;
}
