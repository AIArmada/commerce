<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Contracts\AffiliateRemovalGuard;
use AIArmada\Affiliates\Features\Attribution\Api\AttributionRemoval;
use AIArmada\Affiliates\Features\Attribution\Api\TrackingLinkAdministration;
use AIArmada\Affiliates\Features\Conversions\Api\UnusedAffiliateBalanceRemoval;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalMaintenance;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateRemoval;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Identity\Models\AffiliateClaim;
use AIArmada\Affiliates\Features\Identity\Models\AffiliateCredential;
use AIArmada\Affiliates\Features\Identity\Models\AffiliateOwnershipCapability;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutRemoval;
use AIArmada\Affiliates\Features\Programs\Api\MembershipRemovalGuard;
use AIArmada\Affiliates\Features\Reporting\Api\DailyStatsWriter;
use AIArmada\Affiliates\Features\Upline\Api\RankHistoryRemovalGuard;
use AIArmada\Affiliates\Features\Upline\Api\UplineApi;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class DeleteAffiliate implements AffiliateRemoval
{
    public function __construct(
        private UnusedAffiliateBalanceRemoval $balances,
        private PayoutRemoval $payouts,
        private AttributionRemoval $attributions,
        private TrackingLinkAdministration $links,
        private FraudSignalMaintenance $fraud,
        private DailyStatsWriter $statistics,
        private UplineApi $upline,
        private RankHistoryRemovalGuard $rankHistory,
        private MembershipRemovalGuard $memberships,
    ) {}

    public function delete(string $affiliateId): bool
    {
        $connection = (new Affiliate)->getConnection();

        if ($connection !== (new AffiliateClaim)->getConnection()
            || $connection !== (new AffiliateCredential)->getConnection()
            || $connection !== (new AffiliateOwnershipCapability)->getConnection()) {
            throw new LogicException('Affiliate removal requires a shared identity database connection.');
        }

        $this->fraud->assertConnection($connection->getName(), spl_object_id($connection));

        return $connection->transaction(function () use ($affiliateId): bool {
            $affiliate = Affiliate::query()->forOwner()->lockForUpdate()->findOrFail($affiliateId);

            if ($affiliate->identity_sealed_at !== null
                || AffiliateClaim::query()->where('affiliate_id', $affiliateId)->exists()) {
                throw ValidationException::withMessages(['affiliate' => 'A sealed or claimed affiliate cannot be deleted.']);
            }

            $this->rankHistory->assertCanRemove($affiliateId, spl_object_id($affiliate->getConnection()));
            $this->memberships->assertCanRemove($affiliateId, spl_object_id($affiliate->getConnection()));

            foreach (app()->tagged(AffiliateRemovalGuard::class) as $guard) {
                $guard->assertCanRemove($affiliateId, spl_object_id($affiliate->getConnection()));
            }

            $this->balances->remove($affiliateId);
            $this->payouts->removeUnusedConfiguration($affiliateId);
            $this->attributions->deleteForAffiliate($affiliateId);
            $this->links->deleteForAffiliate($affiliateId);
            $this->fraud->deleteForAffiliate($affiliateId);
            $this->statistics->deleteForAffiliate($affiliateId);
            $this->upline->detachForDeletion($affiliateId);

            AffiliateCredential::query()->where('affiliate_id', $affiliateId)->delete();
            AffiliateOwnershipCapability::query()->where('affiliate_id', $affiliateId)->delete();
            $affiliate->children()->update(['parent_affiliate_id' => null]);

            if (! $affiliate->delete()) {
                throw new LogicException('The affiliate could not be deleted.');
            }

            return true;
        }, attempts: 3);
    }
}
