<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAdministration;
use AIArmada\Affiliates\Features\Identity\Queries\ResolveAffiliateLinkedOwner;
use AIArmada\Affiliates\Features\Identity\Queries\ValidateAffiliateParentAssignment;
use AIArmada\Affiliates\Features\Upline\Api\RankAssignmentGuard;
use AIArmada\Affiliates\Features\Upline\Api\UplineApi;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Paused;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final readonly class AdministerAffiliate implements AffiliateAdministration
{
    public function __construct(
        private ResolveAffiliateLinkedOwner $owners,
        private ValidateAffiliateParentAssignment $parents,
        private UplineApi $upline,
        private RankAssignmentGuard $ranks,
    ) {}

    public function create(array $data): string
    {
        return (new Affiliate)->getConnection()->transaction(function () use ($data): string {
            $data = $this->parents->handle($this->owners->handle($data));
            $affiliate = new Affiliate($this->attributes($data));
            $this->applyOwner($affiliate, $data);
            $this->assertCommission($affiliate);
            $this->assertRank($affiliate);
            $this->synchronizeLifecycleTimestamps($affiliate);
            $affiliate->save();

            if ((bool) config('affiliates.upline.enabled', false) && $affiliate->isCommercialPurpose()) {
                $this->upline->add((string) $affiliate->getKey(), $affiliate->parent_affiliate_id);
            }

            return (string) $affiliate->getKey();
        }, attempts: 3);
    }

    public function update(string $affiliateId, array $data): void
    {
        (new Affiliate)->getConnection()->transaction(function () use ($affiliateId, $data): void {
            if ((bool) config('affiliates.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(Affiliate::class, $affiliateId);
            }

            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);

            if (! $affiliate->isCommercialPurpose()) {
                $data = Arr::only($data, ['status', 'description', 'contact_email', 'website_url']);
            }

            $data = $this->owners->handle($data);

            if (array_key_exists('parent_affiliate_id', $data)) {
                $data = $this->parents->handle($data, $affiliate);
            }

            $affiliate->fill($this->attributes($data));
            $this->applyOwner($affiliate, $data);

            if ($affiliate->isDirty(['commission_type', 'commission_rate'])) {
                $this->assertCommission($affiliate);
            }

            if ($affiliate->isDirty('rank_id')) {
                $this->assertRank($affiliate);
            }

            $parentChanged = $affiliate->isDirty('parent_affiliate_id');

            if ($affiliate->isDirty('status')) {
                $this->synchronizeLifecycleTimestamps($affiliate);
            }

            $affiliate->save();

            if ($parentChanged && (bool) config('affiliates.upline.enabled', false)) {
                $this->upline->move($affiliateId, $affiliate->parent_affiliate_id);
            }
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return Arr::only($data, [
            'code', 'handle', 'name', 'description', 'status',
            'commission_type', 'commission_rate', 'currency', 'parent_affiliate_id',
            'rank_id', 'default_voucher_code', 'tracking_domain', 'payout_terms',
            'contact_email', 'website_url', 'metadata',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function applyOwner(Affiliate $affiliate, array $data): void
    {
        if (isset($data['owner_type'], $data['owner_id'])) {
            $affiliate->forceFill(['owner_type' => $data['owner_type'], 'owner_id' => $data['owner_id']]);
        }
    }

    private function assertCommission(Affiliate $affiliate): void
    {
        if ($affiliate->commission_rate < 0 || ($affiliate->commission_type === CommissionType::Percentage && $affiliate->commission_rate > 10000)) {
            throw ValidationException::withMessages(['data.commission_rate' => 'The commission rate is outside the allowed range.']);
        }
    }

    private function synchronizeLifecycleTimestamps(Affiliate $affiliate): void
    {
        if ($affiliate->status instanceof Active) {
            $affiliate->activated_at = CarbonImmutable::now();
            $affiliate->paused_at = null;
            $affiliate->deactivated_at = null;
        } elseif ($affiliate->status instanceof Paused) {
            $affiliate->paused_at = CarbonImmutable::now();
        } elseif ($affiliate->status instanceof Disabled) {
            $affiliate->deactivated_at = CarbonImmutable::now();
        }
    }

    private function assertRank(Affiliate $affiliate): void
    {
        if ($affiliate->rank_id === null || $affiliate->rank_id === '') {
            return;
        }

        $this->ranks->assertAssignable($affiliate->rank_id);
    }
}
