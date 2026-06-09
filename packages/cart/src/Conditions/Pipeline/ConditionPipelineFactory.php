<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline;

use Closure;

final class ConditionPipelineFactory
{
    /** @var array<string, callable(ConditionPipeline): void> */
    private array $pipelineConfigurators = [];

    public function createEager(): ConditionPipeline
    {
        $pipeline = new ConditionPipeline;

        foreach ($this->pipelineConfigurators as $configurator) {
            $configurator($pipeline);
        }

        return $pipeline;
    }

    public function createLazy(ConditionPipelineContext $context): LazyConditionPipeline
    {
        $pipeline = $this->createEager();

        return new LazyConditionPipeline($context, $pipeline);
    }

    public function configure(Closure $configurator): void
    {
        $this->pipelineConfigurators[] = $configurator;
    }
}
