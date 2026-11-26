<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Events\AffiliateProgramLeft;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfile;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Features\Programs\Api\MembershipAdministration;
use AIArmada\Affiliates\Features\Programs\Api\MembershipRecord;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Features\Programs\Queries\ProgramMembershipQuery;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
            $affiliate = app(AffiliateProfileReader::class)->lock($affiliateId, spl_object_id((new AffiliateProgramMembership)->getConnection()))
                ?? throw new ModelNotFoundException('Affiliate not found.');
            $program = $this->program($programId);
            $this->assertSharedConnection($program);

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
        $connection = (new AffiliateProgramMembership)->getConnection();

        return $connection->transaction(function () use ($membershipId, $data, $connection): MembershipRecord {
            $preview = AffiliateProgramMembership::query()->findOrFail($membershipId);
            $affiliate = app(AffiliateProfileReader::class)->lock($preview->affiliate_id, spl_object_id($connection))
                ?? throw new ModelNotFoundException('Affiliate not found.');
            $program = $this->program($preview->program_id);
            $this->assertSharedConnection($program);
            $membership = AffiliateProgramMembership::query()->lockForUpdate()->findOrFail($membershipId);

            if ($membership->affiliate_id !== $preview->affiliate_id || $membership->program_id !== $preview->program_id) {
                throw new ModelNotFoundException('Program membership changed during administration.');
            }

            $this->persist($membership, $affiliate, $program, $data, false);

            return $this->memberships->project($membership);
        }, attempts: 3);
    }

    public function delete(string $membershipId): void
    {
        $connection = (new AffiliateProgramMembership)->getConnection();

        $connection->transaction(function () use ($membershipId, $connection): void {
            $preview = AffiliateProgramMembership::query()->findOrFail($membershipId);
            $affiliate = app(AffiliateProfileReader::class)->lock($preview->affiliate_id, spl_object_id($connection))
                ?? throw new ModelNotFoundException('Affiliate not found.');
            $program = $this->program($preview->program_id);
            $this->assertSharedConnection($program);
            $membership = AffiliateProgramMembership::query()->lockForUpdate()->findOrFail($membershipId);

            if ($membership->affiliate_id !== $preview->affiliate_id || $membership->program_id !== $preview->program_id) {
                throw new ModelNotFoundException('Program membership changed during administration.');
            }

            $membership->delete();
            $affiliateReferenceId = $affiliate->reference->id;
            $programId = (string) $program->getKey();
            $membershipId = (string) $membership->getKey();
            $connection->afterCommit(fn () => $this->events->dispatch(new AffiliateProgramLeft($affiliateReferenceId, $programId, $membershipId)));
        }, attempts: 3);
    }

    public function suspend(string $membershipId): bool
    {
        $connection = (new AffiliateProgramMembership)->getConnection();

        return $connection->transaction(function () use ($membershipId, $connection): bool {
            $preview = AffiliateProgramMembership::query()->findOrFail($membershipId);
            app(AffiliateProfileReader::class)->lock($preview->affiliate_id, spl_object_id($connection)) ?? throw new ModelNotFoundException('Affiliate not found.');
            $this->program($preview->program_id);
            $membership = AffiliateProgramMembership::query()->lockForUpdate()->findOrFail($membershipId);

            if ($membership->affiliate_id !== $preview->affiliate_id || $membership->program_id !== $preview->program_id) {
                throw new ModelNotFoundException('Program membership changed during administration.');
            }

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
    private function persist(AffiliateProgramMembership $membership, AffiliateProfile $affiliate, AffiliateProgram $program, array $data, bool $creating): void
    {
        $previous = $creating ? null : $membership->status;
        $data['status'] = ($data['status'] ?? null) instanceof MembershipStatus ? $data['status']->value : ($data['status'] ?? $previous?->value ?? MembershipStatus::Pending->value);
        $attributes = Validator::make($data, [
            'tier_id' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['required', Rule::enum(MembershipStatus::class)],
            'applied_at' => ['sometimes', 'required', 'date'],
            'approved_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ])->validate();

        if (isset($attributes['tier_id']) && ! AffiliateProgramTier::query()->where('program_id', $program->getKey())->orderBy('level')->withoutGlobalScope('program_owner')->whereKey($attributes['tier_id'])->exists()) {
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
            $joined = $membership->joinReference($affiliate->reference, $program);
            (new AffiliateProgramMembership)->getConnection()->afterCommit(fn () => $this->events->dispatch(new AffiliateProgramJoined($joined)));
        }
    }

    private function program(string $programId): AffiliateProgram
    {
        return (bool) config('affiliates.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId, includeGlobal: true)
            : AffiliateProgram::query()->findOrFail($programId);
    }

    private function assertSharedConnection(AffiliateProgram $program): void
    {
        $connection = (new AffiliateProgramMembership)->getConnection();

        if ($program->getConnection() !== $connection || (new AffiliateProgramTier)->getConnection() !== $connection) {
            throw new RuntimeException('Program administration requires a shared database connection.');
        }
    }
}
