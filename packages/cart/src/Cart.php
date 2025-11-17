<?php

declare(strict_types=1);

namespace AIArmada\Cart;

use AIArmada\Cart\Conditions\Pipeline\ConditionPipeline;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineContext;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineResult;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Traits\CalculatesTotals;
use AIArmada\Cart\Traits\ManagesConditions;
use AIArmada\Cart\Traits\ManagesDynamicConditions;
use AIArmada\Cart\Traits\ManagesIdentifier;
use AIArmada\Cart\Traits\ManagesInstances;
use AIArmada\Cart\Traits\ManagesItems;
use AIArmada\Cart\Traits\ManagesMetadata;
use AIArmada\Cart\Traits\ManagesStorage;
use AIArmada\Cart\Traits\ProvidesConditionScopes;
use Illuminate\Contracts\Events\Dispatcher;

final class Cart
{
    use CalculatesTotals;
    use ManagesConditions;
    use ManagesDynamicConditions;
    use ManagesIdentifier;
    use ManagesInstances;
    use ManagesItems;
    use ManagesMetadata;
    use ManagesStorage;
    use ProvidesConditionScopes;

    private CartConditionResolver $conditionResolver;

    public function __construct(
        private StorageInterface $storage,
        private string $identifier,
        private ?Dispatcher $events = null,
        private string $instanceName = 'default',
        private bool $eventsEnabled = true,
        ?CartConditionResolver $conditionResolver = null
    ) {
        // Cart is now created when first item is added, not during instantiation
        $this->conditionResolver = $conditionResolver
            ?? (function_exists('app') ? app(CartConditionResolver::class) : new CartConditionResolver());
    }

    public function getConditionResolver(): CartConditionResolver
    {
        return $this->conditionResolver;
    }

    /**
     * Evaluate the condition pipeline for the current cart state.
     *
     * @param  callable(ConditionPipeline):void|null  $configure  Optional pipeline configuration callback
     */
    public function evaluateConditionPipeline(?callable $configure = null): ConditionPipelineResult
    {
        $pipeline = new ConditionPipeline();

        if ($configure !== null) {
            $configure($pipeline);
        }

        return $pipeline->process(ConditionPipelineContext::fromCart($this));
    }

    /**
     * Initialize cart with rules factory for dynamic condition persistence.
     *
     * This method sets up the rules factory and automatically restores
     * any previously persisted dynamic conditions.
     *
     * @param  RulesFactoryInterface  $factory  Factory to create rule closures
     */
    public function withRulesFactory(RulesFactoryInterface $factory): static
    {
        $this->setRulesFactory($factory);
        $this->restoreDynamicConditions();

        return $this;
    }

    /**
     * Get cart version for change tracking
     * Useful for detecting cart modifications and optimistic locking
     *
     * @return int|null Version number or null if not supported by storage driver
     */
    public function getVersion(): ?int
    {
        return $this->storage->getVersion($this->getIdentifier(), $this->instance());
    }

    /**
     * Get cart ID (primary key) from storage
     * Useful for linking carts to external systems like payment gateways, orders, etc.
     *
     * @return string|null Cart UUID or null if not supported by storage driver
     */
    public function getId(): ?string
    {
        return $this->storage->getId($this->getIdentifier(), $this->instance());
    }
}
