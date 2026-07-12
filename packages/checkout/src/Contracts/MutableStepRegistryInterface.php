<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use Closure;

interface MutableStepRegistryInterface
{
    public function register(string $identifier, CheckoutStepInterface $step): void;

    /** @param Closure(): CheckoutStepInterface $factory */
    public function registerLazy(string $identifier, Closure $factory): void;

    /** @param array<string> $order */
    public function setOrder(array $order): void;

    public function enable(string $identifier): void;

    public function disable(string $identifier): void;

    public function replace(string $identifier, CheckoutStepInterface $step): void;

    public function insertBefore(string $beforeIdentifier, string $identifier, CheckoutStepInterface $step): void;

    public function insertAfter(string $afterIdentifier, string $identifier, CheckoutStepInterface $step): void;
}
