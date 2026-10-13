<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Tests\Ledgers\Support;

use AIArmada\Rewards\Contracts\RewardBenefitCouponFulfillment;
use AIArmada\Rewards\Models\RewardBenefitGrant;
use AIArmada\Rewards\Models\RewardBenefitItem;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * In-memory coupon fulfillment for core tests: issues deterministic codes,
 * records canonical usage, and refuses revocation after use.
 */
final class FakeRewardBenefitCouponFulfillment implements RewardBenefitCouponFulfillment
{
    /** @var array<string, string> grant key => code */
    public array $issued = [];

    /** @var array<string, bool> code => used */
    public array $used = [];

    /** @var array<string, bool> code => revoked */
    public array $revoked = [];

    public function issue(RewardBenefitGrant $grant, RewardBenefitItem $item, CarbonImmutable $decided): string
    {
        $code = 'RB-' . mb_strtoupper(mb_substr(hash('sha256', (string) $grant->grant_key), 0, 12));

        $this->issued[(string) $grant->grant_key] = $code;

        return $code;
    }

    public function assertCanonicalUsage(RewardBenefitGrant $grant, array $useEvidence): void
    {
        $code = $this->issued[(string) $grant->grant_key] ?? null;

        if ($code === null) {
            throw new InvalidArgumentException('No coupon was issued for this grant.');
        }

        $cited = $useEvidence['coupon_code'] ?? $code;

        if ($cited !== $code) {
            throw new InvalidArgumentException('Cited coupon usage does not belong to the issued coupon.');
        }

        if (($this->used[$code] ?? false) === false) {
            throw new InvalidArgumentException('No canonical coupon usage recorded for this grant.');
        }
    }

    public function revoke(RewardBenefitGrant $grant): void
    {
        $code = $this->issued[(string) $grant->grant_key] ?? null;

        if ($code === null) {
            throw new InvalidArgumentException('No coupon was issued for this grant.');
        }

        if (($this->used[$code] ?? false) === true) {
            throw new InvalidArgumentException('Coupon already used; open a recovery instead of revoking.');
        }

        $this->revoked[$code] = true;
    }

    public function recordUsage(RewardBenefitGrant $grant): void
    {
        $code = $this->issued[(string) $grant->grant_key] ?? null;

        if ($code !== null) {
            $this->used[$code] = true;
        }
    }
}
