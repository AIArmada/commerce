<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Features\Programs\Api\ProgramTierAdministration;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Support\Arr;
use RuntimeException;

final readonly class AdministerProgramTier implements ProgramTierAdministration
{
    public function create(string $programId, array $data): string
    {
        return (new AffiliateProgramTier)->getConnection()->transaction(function () use ($programId, $data): string {
            $this->lockProgram($programId);

            $tier = AffiliateProgramTier::query()->create([
                ...$this->attributes($data),
                'program_id' => $programId,
            ]);

            return (string) $tier->getKey();
        }, attempts: 3);
    }

    public function update(string $tierId, array $data): void
    {
        (new AffiliateProgramTier)->getConnection()->transaction(function () use ($tierId, $data): void {
            $tier = $this->lockTier($tierId);
            $tier->update($this->attributes($data));
        }, attempts: 3);
    }

    public function delete(string $tierId): void
    {
        (new AffiliateProgramTier)->getConnection()->transaction(function () use ($tierId): void {
            $tier = $this->lockTier($tierId);

            if ((new AffiliateProgramMembership)->getConnection() !== $tier->getConnection()) {
                throw new RuntimeException('Tier deletion requires a shared database connection.');
            }

            AffiliateProgramMembership::query()
                ->withoutGlobalScope('affiliate_owner')
                ->where('program_id', $tier->program_id)
                ->where('tier_id', $tierId)
                ->update(['tier_id' => null]);

            $tier->delete();
        }, attempts: 3);
    }

    private function lockTier(string $tierId): AffiliateProgramTier
    {
        $tier = AffiliateProgramTier::query()->findOrFail($tierId);
        $this->lockProgram($tier->program_id);

        return AffiliateProgramTier::query()->lockForUpdate()->findOrFail($tierId);
    }

    private function lockProgram(string $programId): void
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId);
        }

        $program = AffiliateProgram::query()->lockForUpdate()->findOrFail($programId);

        if ($program->getConnection() !== (new AffiliateProgramTier)->getConnection()) {
            throw new RuntimeException('Tier administration requires a shared database connection.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return Arr::only($data, ['name', 'level', 'commission_rate_basis_points', 'min_conversions', 'min_revenue', 'min_revenue_currency', 'benefits']);
    }
}
