<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Fulfillment;

use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Exceptions\ReferralFulfillmentException;
use AIArmada\Referrals\Models\ReferralReward;
use Illuminate\Contracts\Container\Container;

final class ReferralRewardFulfillmentRouter implements ReferralRewardFulfillment
{
    /**
     * @param  array<string, class-string<ReferralRewardFulfillment>>  $drivers
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $drivers,
    ) {}

    public function fulfill(ReferralReward $reward): ReferralFulfillmentResult
    {
        return $this->adapter($reward)->fulfill($reward);
    }

    public function reverse(ReferralReward $reward, string $reason): ReferralFulfillmentResult
    {
        return $this->adapter($reward)->reverse($reward, $reason);
    }

    private function adapter(ReferralReward $reward): ReferralRewardFulfillment
    {
        $driver = $reward->driver !== ''
            ? $reward->driver
            : (string) config('referrals.fulfillment.default_driver', 'manual');
        $driverConfig = config('referrals.fulfillment.drivers.' . $driver);
        $adapterClass = $this->drivers[$driver] ?? null;

        if (! is_array($driverConfig)
            || ! ($driverConfig['enabled'] ?? false)
            || ! is_string($adapterClass)
            || ! class_exists($adapterClass)) {
            throw new ReferralFulfillmentException(sprintf('Referral reward driver [%s] is not configured or available.', $driver));
        }

        $adapter = $this->container->make($adapterClass);

        if (! $adapter instanceof ReferralRewardFulfillment) {
            throw new ReferralFulfillmentException(sprintf('Referral reward driver [%s] has an invalid adapter binding.', $driver));
        }

        return $adapter;
    }
}
