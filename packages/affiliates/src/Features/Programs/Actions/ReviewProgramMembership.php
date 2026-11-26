<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Features\Programs\Api\MembershipReviewApi;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
        $connection = (new AffiliateProgramMembership)->getConnection();

        return $connection->transaction(function () use ($membershipId, $status, $approvedBy, $connection): bool {
            $preview = AffiliateProgramMembership::query()->findOrFail($membershipId);
            $affiliate = app(AffiliateProfileReader::class)->lock($preview->affiliate_id, spl_object_id($connection))
                ?? throw new ModelNotFoundException('Affiliate not found.');
            $program = (bool) config('affiliates.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $preview->program_id, includeGlobal: true)
                : AffiliateProgram::query()->findOrFail($preview->program_id);
            $membership = AffiliateProgramMembership::query()->lockForUpdate()->findOrFail($membershipId);

            if ($membership->affiliate_id !== $preview->affiliate_id || $membership->program_id !== $preview->program_id) {
                throw new ModelNotFoundException('Program membership changed during review.');
            }

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

            $joined = $membership->joinReference($affiliate->reference, $program);
            $connection->afterCommit(fn () => $this->events->dispatch(new AffiliateProgramJoined($joined)));

            return true;
        }, attempts: 3);
    }
}
