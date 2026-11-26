<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Features\Programs\Api\PortalProgramRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramPortalReader;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class ProgramPortalQuery implements ProgramPortalReader
{
    public function __construct(private ProgramEligibilityQuery $eligibility) {}

    public function programExists(string $programId): bool
    {
        return AffiliateProgram::query()->forOwner(includeGlobal: false)->whereKey($programId)->exists();
    }

    public function programs(string $affiliateId): array
    {
        $affiliate = app(AffiliateProfileReader::class)->find($affiliateId, spl_object_id((new AffiliateProgramMembership)->getConnection()))
            ?? throw new ModelNotFoundException('Affiliate not found.');
        $programs = AffiliateProgram::query()->forOwner(includeGlobal: false)->active()->public()->orderBy('id')->get();

        $memberships = AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliateId)
            ->whereIn('program_id', $programs->modelKeys())
            ->get()
            ->keyBy('program_id');

        $eligible = array_fill_keys($this->eligibility->eligibleIds(
            $affiliate,
            $programs->filter(fn (AffiliateProgram $program): bool => ! $memberships->has($program->id)),
        ), true);

        $activeIds = $programs->filter(fn (AffiliateProgram $program): bool => $memberships->get($program->id)?->isActive() ?? false)->modelKeys();
        $creatives = AffiliateProgramCreative::query()->whereIn('program_id', $activeIds)->with('media')->orderBy('id')->get()->groupBy('program_id');

        return $programs->map(function (AffiliateProgram $program) use ($eligible, $memberships, $creatives): PortalProgramRecord {
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
                canJoin: isset($eligible[$program->id]),
                creatives: $active
                    ? $creatives->get($program->id, collect())->map(fn (AffiliateProgramCreative $creative): array => [
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
