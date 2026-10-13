<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Contracts\RewardPayoutMethodCatalog;
use InvalidArgumentException;

/**
 * Manual payout method catalog: the package default.
 *
 * Serves the configured `rewards.payouts.methods` with zero fees and
 * operator destinations. Every configured method is supported: manual
 * rails require operator handling, never credentials. Hosts with
 * automated rails bind a catalog that reports installed fee schedules
 * and credential prerequisites.
 */
final class ManualRewardPayoutMethodCatalog implements RewardPayoutMethodCatalog
{
    /**
     * @return list<string>
     */
    public function registered(): array
    {
        $methods = config('rewards.payouts.methods', ['manual']);

        return array_values(array_filter(
            is_array($methods) ? $methods : ['manual'],
            static fn (mixed $method): bool => is_string($method) && $method !== '',
        ));
    }

    public function isRegistered(string $method): bool
    {
        return in_array($method, $this->registered(), true);
    }

    public function isManual(string $method): bool
    {
        return $this->isRegistered($method);
    }

    public function isSupported(string $method): bool
    {
        return $this->isRegistered($method) && $this->missingCredentials($method) === [];
    }

    public function describe(): array
    {
        $described = [];

        foreach ($this->registered() as $method) {
            $described[$method] = [
                'registered' => true,
                'manual' => true,
                'supported' => $this->isSupported($method),
                'missing_credentials' => $this->missingCredentials($method),
            ];
        }

        return $described;
    }

    public function missingCredentials(string $method): array
    {
        // Operator rails carry no provider credentials.
        return $this->isRegistered($method) ? [] : ["rewards.payouts.methods (unknown method [{$method}])"];
    }

    public function feeFor(string $method, int $amountMinor, string $currency): int
    {
        $this->assertKnown($method);

        return 0;
    }

    public function detailsFor(string $method, string $accountRef, array $audit = []): array
    {
        $this->assertKnown($method);

        return $audit;
    }

    public function destinationRefFor(string $method, array $details): ?string
    {
        $this->assertKnown($method);

        return null;
    }

    public function validateDestination(string $method, array $details): array
    {
        $this->assertKnown($method);

        return [];
    }

    private function assertKnown(string $method): void
    {
        if (! $this->isRegistered($method)) {
            throw new InvalidArgumentException("Payout method [{$method}] is not registered.");
        }
    }
}
