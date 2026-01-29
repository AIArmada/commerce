<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Services;

use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Exceptions\CheckoutStepException;
use Closure;

final class CheckoutStepRegistry implements CheckoutStepRegistryInterface
{
    /** @var array<string, CheckoutStepInterface> */
    private array $steps = [];

    /** @var array<string, Closure(): CheckoutStepInterface> */
    private array $lazyFactories = [];

    /** @var array<string> */
    private array $order = [];

    /** @var array<string, bool> */
    private array $enabled = [];

    public function register(string $identifier, CheckoutStepInterface $step): void
    {
        $this->steps[$identifier] = $step;
        unset($this->lazyFactories[$identifier]);
        $this->enabled[$identifier] ??= true;

        if (! in_array($identifier, $this->order, true)) {
            $this->order[] = $identifier;
        }
    }

    /**
     * @param  Closure(): CheckoutStepInterface  $factory
     */
    public function registerLazy(string $identifier, Closure $factory): void
    {
        $this->lazyFactories[$identifier] = $factory;
        $this->enabled[$identifier] ??= true;

        if (! in_array($identifier, $this->order, true)) {
            $this->order[] = $identifier;
        }
    }

    public function get(string $identifier): ?CheckoutStepInterface
    {
        // Resolve lazy factory if not yet resolved
        if (! isset($this->steps[$identifier]) && isset($this->lazyFactories[$identifier])) {
            $this->steps[$identifier] = ($this->lazyFactories[$identifier])();
            unset($this->lazyFactories[$identifier]);
        }

        return $this->steps[$identifier] ?? null;
    }

    public function has(string $identifier): bool
    {
        return isset($this->steps[$identifier]) || isset($this->lazyFactories[$identifier]);
    }

    /**
     * @return array<string, CheckoutStepInterface>
     */
    public function all(): array
    {
        // Resolve all lazy factories
        foreach (array_keys($this->lazyFactories) as $identifier) {
            $this->get($identifier);
        }

        return $this->steps;
    }

    /**
     * @return array<CheckoutStepInterface>
     */
    public function getOrderedSteps(): array
    {
        $ordered = [];

        foreach ($this->order as $identifier) {
            if ($this->isEnabled($identifier) && $this->has($identifier)) {
                $step = $this->get($identifier);
                if ($step !== null) {
                    $ordered[] = $step;
                }
            }
        }

        return $ordered;
    }

    /**
     * @param  array<string>  $order
     */
    public function setOrder(array $order): void
    {
        $this->order = $order;
    }

    public function enable(string $identifier): void
    {
        $this->enabled[$identifier] = true;
    }

    public function disable(string $identifier): void
    {
        $this->enabled[$identifier] = false;
    }

    public function isEnabled(string $identifier): bool
    {
        return $this->enabled[$identifier] ?? true;
    }

    public function replace(string $identifier, CheckoutStepInterface $step): void
    {
        if (! $this->has($identifier)) {
            throw CheckoutStepException::stepNotFound($identifier);
        }

        $this->steps[$identifier] = $step;
    }

    public function insertBefore(string $beforeIdentifier, string $identifier, CheckoutStepInterface $step): void
    {
        $position = array_search($beforeIdentifier, $this->order, true);

        if ($position === false) {
            throw CheckoutStepException::stepNotFound($beforeIdentifier);
        }

        $this->steps[$identifier] = $step;
        $this->enabled[$identifier] = true;

        array_splice($this->order, $position, 0, [$identifier]);
    }

    public function insertAfter(string $afterIdentifier, string $identifier, CheckoutStepInterface $step): void
    {
        $position = array_search($afterIdentifier, $this->order, true);

        if ($position === false) {
            throw CheckoutStepException::stepNotFound($afterIdentifier);
        }

        $this->steps[$identifier] = $step;
        $this->enabled[$identifier] = true;

        array_splice($this->order, $position + 1, 0, [$identifier]);
    }

    /**
     * @return array<string>
     */
    public function getOrder(): array
    {
        return $this->order;
    }

    /**
     * @return array<string>
     */
    public function getEnabledStepIdentifiers(): array
    {
        return array_filter($this->order, fn (string $id) => $this->isEnabled($id));
    }
}
