<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use AIArmada\Cart\Contracts\ConditionProviderInterface;

final class ConditionProviderRegistry
{
    /**
     * @var array<string, callable(): ConditionProviderInterface>
     */
    private array $providers = [];

    /**
     * @param  ConditionProviderInterface|class-string<ConditionProviderInterface>  $provider
     */
    public function register(ConditionProviderInterface | string $provider): void
    {
        if (is_string($provider)) {
            $this->providers[$provider] = static fn (): ConditionProviderInterface => app($provider);

            return;
        }

        $this->providers[$provider::class] = static fn (): ConditionProviderInterface => $provider;
    }

    /**
     * @return array<int, ConditionProviderInterface>
     */
    public function all(): array
    {
        return array_map(static fn (callable $factory): ConditionProviderInterface => $factory(), array_values($this->providers));
    }

    /**
     * @return array<int, string>
     */
    public function providerKeys(): array
    {
        return array_keys($this->providers);
    }
}
