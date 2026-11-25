<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Events\AffiliateProgramLeft;
use AIArmada\Affiliates\Features\Programs\Api\MembershipAdministration;
use AIArmada\Affiliates\Features\Programs\Api\MembershipRecord;
use AIArmada\Affiliates\Features\Programs\Queries\ProgramMembershipQuery;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class AdministerProgramMembership implements MembershipAdministration
{
    public function __construct(private ProgramMembershipQuery $memberships, private Dispatcher $events) {}

    public function create(string $affiliateId, string $programId, array $data): MembershipRecord
    {
        return (new AffiliateProgramMembership)->getConnection()->transaction(function () use ($affiliateId, $programId, $data): MembershipRecord {
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            $program = $this->program($programId);
            $this->assertSharedConnection($affiliate, $program);

            if (AffiliateProgramMembership::query()->where('affiliate_id', $affiliateId)->where('program_id', $programId)->exists()) {
                throw ValidationException::withMessages(['program_id' => 'This affiliate is already a member of the program.']);
            }

            $membership = new AffiliateProgramMembership([
                'affiliate_id' => $affiliateId,
                'program_id' => $programId,
            ]);
            $this->persist($membership, $affiliate, $program, $data, true);

            return $this->memberships->project($membership, true);
        }, attempts: 3);
    }

    public function update(string $membershipId, array $data): MembershipRecord
    {
        return (new AffiliateProgramMembership)->getConnection()->transaction(function () use ($membershipId, $data): MembershipRecord {
            $membership = AffiliateProgramMembership::query()->lockForUpdate()->findOrFail($membershipId);
            $affiliate = Affiliate::query()->findOrFail($membership->affiliate_id);
            $program = $this->program($membership->program_id);
            $this->assertSharedConnection($affiliate, $program);
            $this->persist($membership, $affiliate, $program, $data, false);

            return $this->memberships->project($membership);
        }, attempts: 3);
    }

    public function delete(string $membershipId): void
    {
        (new AffiliateProgramMembership)->getConnection()->transaction(function () use ($membershipId): void {
            $membership = AffiliateProgramMembership::query()->lockForUpdate()->findOrFail($membershipId);
            $affiliate = Affiliate::query()->findOrFail($membership->affiliate_id);
            $program = $this->program($membership->program_id);
            $this->assertSharedConnection($affiliate, $program);

            $membership->delete();
            $this->events->dispatch(new AffiliateProgramLeft($affiliate, $program));
        }, attempts: 3);
    }

    public function suspend(string $membershipId): bool
    {
        return (new AffiliateProgramMembership)->getConnection()->transaction(function () use ($membershipId): bool {
            $membership = AffiliateProgramMembership::query()->lockForUpdate()->findOrFail($membershipId);
            $this->program($membership->program_id);

            if ($membership->status === MembershipStatus::Suspended) {
                return false;
            }

            $membership->update([
                'status' => MembershipStatus::Suspended,
                'suspended_at' => CarbonImmutable::now(),
            ]);

            return true;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    private function persist(AffiliateProgramMembership $membership, Affiliate $affiliate, AffiliateProgram $program, array $data, bool $creating): void
    {
        $previous = $creating ? null : $membership->status;
        $data['status'] = ($data['status'] ?? null) instanceof MembershipStatus ? $data['status']->value : ($data['status'] ?? $previous?->value ?? MembershipStatus::Pending->value);
        $attributes = Validator::make($data, [
            'tier_id' => ['sometimes', 'nullable', 'string'],
            'status' => ['required', Rule::enum(MembershipStatus::class)],
            'applied_at' => ['sometimes', 'required', 'date'],
            'approved_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ])->validate();

        if (isset($attributes['tier_id']) && ! $program->tiers()->withoutGlobalScope('program_owner')->whereKey($attributes['tier_id'])->exists()) {
            throw ValidationException::withMessages(['tier_id' => 'The selected tier does not belong to this program.']);
        }

        $status = MembershipStatus::from((string) $attributes['status']);
        $now = CarbonImmutable::now();
        $attributes['applied_at'] ??= $creating ? $now : $membership->applied_at;

        if ($status === MembershipStatus::Approved) {
            $attributes['approved_at'] ??= $membership->approved_at ?? $now;
            $attributes['rejected_at'] = null;
            $attributes['suspended_at'] = null;
        } elseif ($status === MembershipStatus::Rejected) {
            $attributes['rejected_at'] = $membership->rejected_at ?? $now;
            $attributes['suspended_at'] = null;
            $attributes['approved_at'] = null;
            $attributes['approved_by'] = null;
        } elseif ($status === MembershipStatus::Suspended) {
            $attributes['suspended_at'] = $membership->suspended_at ?? $now;
            $attributes['rejected_at'] = null;
        } else {
            $attributes['approved_at'] = null;
            $attributes['approved_by'] = null;
            $attributes['rejected_at'] = null;
            $attributes['suspended_at'] = null;
        }

        $membership->fill($attributes)->save();

        if ($status === MembershipStatus::Approved && $previous !== MembershipStatus::Approved) {
            $this->events->dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));
        }
    }

    private function program(string $programId): AffiliateProgram
    {
        return (bool) config('affiliates.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId, includeGlobal: true)
            : AffiliateProgram::query()->findOrFail($programId);
    }

    private function assertSharedConnection(Affiliate $affiliate, AffiliateProgram $program): void
    {
        $connection = (new AffiliateProgramMembership)->getConnection();

        if ($affiliate->getConnection() !== $connection || $program->getConnection() !== $connection) {
            throw new RuntimeException('Program administration requires a shared database connection.');
        }
    }
}
