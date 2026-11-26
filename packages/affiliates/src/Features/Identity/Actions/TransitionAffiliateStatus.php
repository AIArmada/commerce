<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Contracts\QualifiesForOpenApproval;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionInspectionReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycle;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Identity\Queries\GuardAffiliateAccess;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\States\Pending;
use Carbon\CarbonImmutable;

final readonly class TransitionAffiliateStatus implements AffiliateLifecycle
{
    public function __construct(private QualifiesForOpenApproval $openApproval, private ConversionInspectionReader $conversions, private GuardAffiliateAccess $access) {}

    public function approve(string $affiliateId): bool
    {
        return (new Affiliate)->getConnection()->transaction(function () use ($affiliateId): bool {
            return $this->activate($this->lockAffiliate($affiliateId));
        }, attempts: 3);
    }

    public function reject(string $affiliateId): bool
    {
        return (new Affiliate)->getConnection()->transaction(function () use ($affiliateId): bool {
            $affiliate = $this->lockAffiliate($affiliateId);

            return $affiliate->status instanceof Pending && $this->deactivate($affiliate);
        }, attempts: 3);
    }

    public function pause(string $affiliateId): bool
    {
        return (new Affiliate)->getConnection()->transaction(function () use ($affiliateId): bool {
            $affiliate = $this->lockAffiliate($affiliateId);

            if (! $affiliate->status instanceof Active) {
                return false;
            }

            $affiliate->status = new Paused($affiliate);
            $affiliate->paused_at = CarbonImmutable::now();
            $affiliate->deactivated_at = null;
            $affiliate->save();

            return true;
        }, attempts: 3);
    }

    public function disable(string $affiliateId): bool
    {
        return (new Affiliate)->getConnection()->transaction(function () use ($affiliateId): bool {
            return $this->deactivate($this->lockAffiliate($affiliateId));
        }, attempts: 3);
    }

    public function approveFromConversion(string $conversionId): void
    {
        $connection = (new Affiliate)->getConnection();

        $this->conversions->assertConnection(spl_object_id($connection));

        if (! $this->openApproval->qualifies($conversionId)) {
            return;
        }

        $connection->transaction(function () use ($conversionId, $connection): void {
            $conversion = $this->conversions->lock($conversionId, spl_object_id($connection));

            if ($conversion === null) {
                return;
            }

            $affiliate = Affiliate::query()->whereKey($conversion->affiliateId)->lockForUpdate()->first();

            if ($affiliate === null || ! $affiliate->status instanceof Pending || ! $this->openApproval->qualifies($conversionId)) {
                return;
            }

            $affiliate->metadata = array_merge($affiliate->metadata ?? [], ['open_approved_by_conversion' => $conversionId]);
            $this->activate($affiliate);
        }, attempts: 3);
    }

    private function lockAffiliate(string $affiliateId): Affiliate
    {
        return $this->access->lockedAffiliate($affiliateId);
    }

    private function activate(Affiliate $affiliate): bool
    {
        if (! $affiliate->status instanceof Pending) {
            return false;
        }

        $affiliate->status = new Active($affiliate);
        $affiliate->activated_at = CarbonImmutable::now();
        $affiliate->deactivated_at = null;
        $affiliate->paused_at = null;
        $affiliate->save();

        return true;
    }

    private function deactivate(Affiliate $affiliate): bool
    {
        if ($affiliate->status instanceof Disabled) {
            return false;
        }

        $affiliate->status = new Disabled($affiliate);
        $affiliate->deactivated_at = CarbonImmutable::now();
        $affiliate->paused_at = null;
        $affiliate->save();

        return true;
    }
}
