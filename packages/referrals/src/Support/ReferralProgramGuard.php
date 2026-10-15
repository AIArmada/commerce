<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Referrals\Enums\ReferralRewardSide;
use AIArmada\Referrals\Exceptions\ReferralInviteNotFoundException;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Models\ReferralReward;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ReferralProgramGuard
{
    public function program(ReferralProgram | string $program, bool $lock = false): ReferralProgram
    {
        $id = $program instanceof ReferralProgram ? (string) $program->getKey() : $program;

        if ((bool) config('referrals.owner.enabled', true)) {
            // Writes never widen to global rows: `include_global` is read
            // visibility only (referrals/docs/03-configuration.md), so the
            // guard resolves with includeGlobal=false unconditionally.
            // Explicit-global contexts keep working because OwnerQuery
            // returns global-only rows when the resolved owner is null.
            $resolved = OwnerWriteGuard::findOrFailForOwner(
                modelClass: ReferralProgram::class,
                id: $id,
                includeGlobal: false,
            );
        } else {
            $resolved = ReferralProgram::query()->whereKey($id)->firstOrFail();
        }

        if (! $resolved instanceof ReferralProgram) {
            throw (new ModelNotFoundException)->setModel(ReferralProgram::class, $id);
        }

        if (! $lock) {
            return $resolved;
        }

        $locked = ReferralProgram::query()->whereKey($resolved->getKey())->lockForUpdate()->firstOrFail();

        return $locked;
    }

    public function invite(ReferralInvite | string $invite, bool $lock = false): ReferralInvite
    {
        $id = $invite instanceof ReferralInvite ? (string) $invite->getKey() : $invite;
        $scoped = ReferralInvite::query()
            ->whereHas('program')
            ->whereKey($id)
            ->firstOrFail();

        $program = $this->program((string) $scoped->program_id);
        $query = ReferralInvite::query()
            ->where('program_id', $program->getKey())
            ->whereKey($id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function inviteByToken(string $token, bool $lock = false): ReferralInvite
    {
        $tokenHash = hash('sha256', $token);
        $scoped = ReferralInvite::query()
            ->whereHas('program')
            ->where('token_hash', $tokenHash)
            ->first();
        $storedTokenHash = $scoped instanceof ReferralInvite ? (string) $scoped->token_hash : str_repeat('0', 64);

        if (! ($scoped instanceof ReferralInvite) || ! hash_equals($storedTokenHash, $tokenHash)) {
            throw ReferralInviteNotFoundException::invalid();
        }

        return $this->invite((string) $scoped->getKey(), $lock);
    }

    public function inviteFromIdOrToken(string $inviteOrToken): ReferralInvite
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $inviteOrToken) === 1) {
            try {
                return $this->invite($inviteOrToken);
            } catch (ModelNotFoundException) {
                throw ReferralInviteNotFoundException::invalid();
            }
        }

        return $this->inviteByToken($inviteOrToken);
    }

    public function reward(ReferralReward | string $reward, bool $lock = false): ReferralReward
    {
        $id = $reward instanceof ReferralReward ? (string) $reward->getKey() : $reward;
        $scoped = ReferralReward::query()
            ->whereHas('program')
            ->whereKey($id)
            ->firstOrFail();

        $program = $this->program((string) $scoped->program_id);
        $invite = ReferralInvite::query()
            ->where('program_id', $program->getKey())
            ->whereKey($scoped->invite_id)
            ->firstOrFail();
        $query = ReferralReward::query()
            ->where('program_id', $program->getKey())
            ->where('invite_id', $invite->getKey())
            ->whereKey($id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function rewardForInviteSide(
        ReferralInvite | string $invite,
        ReferralRewardSide $side,
        bool $lock = false,
    ): ReferralReward {
        $resolvedInvite = $this->invite($invite);
        $program = $this->program((string) $resolvedInvite->program_id);
        $query = ReferralReward::query()
            ->where('program_id', $program->getKey())
            ->where('invite_id', $resolvedInvite->getKey())
            ->where('side', $side->value);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }
}
