<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Contracts\CreativeAssetRemoval;
use AIArmada\Affiliates\Features\Commissions\Api\ProgramCommissionCleanup;
use AIArmada\Affiliates\Features\Programs\Api\ProgramAdministration;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Support\Arr;
use RuntimeException;

final readonly class AdministerProgram implements ProgramAdministration
{
    public function __construct(
        private ProgramCommissionCleanup $commissions,
        private CreativeAssetRemoval $assets,
    ) {}

    public function create(array $data): string
    {
        return (new AffiliateProgram)->getConnection()->transaction(function () use ($data): string {
            $program = new AffiliateProgram($this->attributes($data));

            if ((bool) config('affiliates.owner.enabled', false)) {
                $owner = OwnerContext::resolveConfigured(AffiliateProgram::ownerScopeConfig());
                OwnerContext::assertResolvedOrExplicitGlobal($owner, 'Program creation requires an owner context or explicit global context.');

                if ($owner !== null) {
                    $program->owner()->associate($owner);
                }
            }

            $program->save();

            return (string) $program->getKey();
        }, attempts: 3);
    }

    public function update(string $programId, array $data): void
    {
        (new AffiliateProgram)->getConnection()->transaction(function () use ($programId, $data): void {
            $this->lockProgram($programId)->update($this->attributes($data));
        }, attempts: 3);
    }

    public function delete(string $programId): void
    {
        (new AffiliateProgram)->getConnection()->transaction(function () use ($programId): void {
            $program = $this->lockProgram($programId);
            $connection = $program->getConnection();

            foreach ([new AffiliateProgramMembership, new AffiliateProgramTier, new AffiliateProgramCreative] as $model) {
                if ($model->getConnection() !== $connection) {
                    throw new RuntimeException('Program deletion requires a shared database connection.');
                }
            }

            AffiliateProgramMembership::query()->withoutGlobalScope('affiliate_owner')->where('program_id', $programId)->delete();
            AffiliateProgramTier::query()->withoutGlobalScope('program_owner')->where('program_id', $programId)->delete();
            $this->commissions->deleteForProgram($programId);

            AffiliateProgramCreative::query()
                ->withoutGlobalScope('program_owner')
                ->where('program_id', $programId)
                ->eachById(function (AffiliateProgramCreative $creative): void {
                    $this->assets->remove((string) $creative->getKey(), $creative->getMorphClass(), spl_object_id($creative->getConnection()));

                    if (! $creative->deletePreservingMedia()) {
                        throw new RuntimeException('Program creative deletion failed.');
                    }
                });

            $program->delete();
        }, attempts: 3);
    }

    private function lockProgram(string $programId): AffiliateProgram
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId);
        }

        return AffiliateProgram::query()->lockForUpdate()->findOrFail($programId);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return Arr::only($data, [
            'name', 'slug', 'description', 'status', 'starts_at', 'ends_at', 'requires_approval',
            'visibility', 'default_commission_rate_basis_points', 'paused_at', 'archived_at',
            'commission_type', 'currency', 'cookie_lifetime_days', 'terms_url', 'eligibility_rules', 'metadata',
        ]);
    }
}
