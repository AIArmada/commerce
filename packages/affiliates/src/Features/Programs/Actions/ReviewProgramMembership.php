<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Features\Programs\Api\MembershipReviewApi;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class ReviewProgramMembership implements MembershipReviewApi
{
    public function __construct(private Dispatcher $events) {}

    public function approve(string $membershipId, ?string $approvedBy = null): bool
    {
        return $this->review($membershipId, MembershipStatus::Approved, $approvedBy);
    }

    public function reject(string $membershipId): bool
    {
        return $this->review($membershipId, MembershipStatus::Rejected, null);
    }

    private function review(string $membershipId, MembershipStatus $status, ?string $approvedBy): bool
    {
        return (new AffiliateProgramMembership)->getConnection()->transaction(function () use ($membershipId, $status, $approvedBy): bool {
            $membership = AffiliateProgramMembership::query()->lockForUpdate()->findOrFail($membershipId);
            $program = (bool) config('affiliates.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $membership->program_id, includeGlobal: true)
                : AffiliateProgram::query()->findOrFail($membership->program_id);

            $affiliate = app(AffiliateProfileReader::class)->lock($membership->affiliate_id, spl_object_id((new AffiliateProgramMembership)->getConnection()))
                ?? throw new ModelNotFoundException('Affiliate not found.');

            if ($membership->status !== MembershipStatus::Pending) {
                return false;
            }

            if ($status === MembershipStatus::Rejected) {
                $membership->update([
                    'status' => MembershipStatus::Rejected,
                    'rejected_at' => CarbonImmutable::now(),
                ]);

                return true;
            }

            $membership->update([
                'status' => MembershipStatus::Approved,
                'approved_at' => CarbonImmutable::now(),
                'approved_by' => $approvedBy,
                'rejected_at' => null,
                'suspended_at' => null,
            ]);

            $this->events->dispatch(new AffiliateProgramJoined($membership->joinReference($affiliate->reference, $program)));

            return true;
        }, attempts: 3);
    }
}
