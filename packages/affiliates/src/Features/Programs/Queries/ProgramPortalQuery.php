<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Programs\Api\PortalProgramRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramPortalReader;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;

final readonly class ProgramPortalQuery implements ProgramPortalReader
{
    public function programExists(string $programId): bool
    {
        return AffiliateProgram::query()->forOwner(includeGlobal: false)->whereKey($programId)->exists();
    }

    public function programs(string $affiliateId): array
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        $programs = AffiliateProgram::query()->forOwner(includeGlobal: false)->active()->public()->orderBy('id')->get();

        $memberships = AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliateId)
            ->whereIn('program_id', $programs->modelKeys())
            ->get()
            ->keyBy('program_id');

        $programs->filter(fn (AffiliateProgram $program): bool => $memberships->get($program->id)?->isActive() ?? false)
            ->load('creatives.media');

        return $programs->map(function (AffiliateProgram $program) use ($affiliate, $memberships): PortalProgramRecord {
            $membership = $memberships->get($program->id);
            $active = $membership?->isActive() ?? false;

            return new PortalProgramRecord(
                id: $program->id,
                name: $program->name,
                description: $program->description,
                status: $program->status->value,
                statusLabel: $program->status->label(),
                requiresApproval: $program->requires_approval,
                membershipStatus: $membership?->status->value,
                membershipStatusLabel: $membership?->status->label(),
                activeMembership: $active,
                canJoin: $membership === null && $program->canJoin($affiliate),
                creatives: $active
                    ? $program->creatives->map(fn (AffiliateProgramCreative $creative): array => [
                        'id' => (string) $creative->getKey(),
                        'name' => (string) $creative->name,
                        'type' => (string) $creative->type,
                        'download_url' => $creative->getAssetUrl(),
                        'destination_url' => (string) $creative->safeDestinationUrl(),
                    ])->values()->all()
                    : [],
            );
        })->values()->all();
    }
}
