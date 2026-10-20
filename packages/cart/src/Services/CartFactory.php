<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Cart\Conditions\Handlers\ConditionTypeHandlerRegistry;
use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Contracts\Events\Dispatcher;

final class CartFactory
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly CartConditionResolver $conditionResolver,
        private readonly ?ConditionProviderRegistry $conditionProviderRegistry = null,
        private readonly ?ConditionTypeHandlerRegistry $conditionTypeHandlerRegistry = null,
        private readonly ?Dispatcher $events = null,
        private readonly bool $eventsEnabled = true,
    ) {}

    /**
     * Clone the factory with a different storage while keeping every collaborator.
     *
     * Used by the ambient cart manager to build one cart per owner partition
     * without losing the registered condition providers and handlers.
     */
    public function withStorage(StorageInterface $storage): self
    {
        return new self(
            storage: $storage,
            conditionResolver: $this->conditionResolver,
            conditionProviderRegistry: $this->conditionProviderRegistry,
            conditionTypeHandlerRegistry: $this->conditionTypeHandlerRegistry,
            events: $this->events,
            eventsEnabled: $this->eventsEnabled,
        );
    }

    public function make(
        string $identifier,
        string $instanceName = 'default',
        ?Dispatcher $events = null,
        ?bool $eventsEnabled = null,
    ): Cart {
        return new Cart(
            storage: $this->storage,
            identifier: $identifier,
            events: $events ?? $this->events,
            instanceName: $instanceName,
            eventsEnabled: $eventsEnabled ?? $this->eventsEnabled,
            conditionResolver: $this->conditionResolver,
            conditionProviderRegistry: $this->conditionProviderRegistry,
            conditionTypeHandlerRegistry: $this->conditionTypeHandlerRegistry,
        );
    }

    public function cloneForIdentifier(Cart $cart, string $newIdentifier): Cart
    {
        return new Cart(
            storage: $this->storage,
            identifier: $newIdentifier,
            events: $cart->getEvents(),
            instanceName: $cart->instance(),
            eventsEnabled: $cart->isEventsEnabled(),
            conditionResolver: $this->conditionResolver,
            conditionProviderRegistry: $this->conditionProviderRegistry,
            conditionTypeHandlerRegistry: $this->conditionTypeHandlerRegistry,
        );
    }

    public function cloneForInstance(Cart $cart, string $newInstance): Cart
    {
        return new Cart(
            storage: $this->storage,
            identifier: $cart->getIdentifier(),
            events: $cart->getEvents(),
            instanceName: $newInstance,
            eventsEnabled: $cart->isEventsEnabled(),
            conditionResolver: $this->conditionResolver,
            conditionProviderRegistry: $this->conditionProviderRegistry,
            conditionTypeHandlerRegistry: $this->conditionTypeHandlerRegistry,
        );
    }
}
