<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\Referrals\Data\ReferralInviteCreationResult;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Events\ReferralInviteCreated;
use AIArmada\Referrals\Exceptions\ReferralAlreadyClaimedException;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Support\ReferralContactNormalizer;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateReferralInvite
{
    use AsAction;

    public function handle(
        ReferralProgram $program,
        Model $referrer,
        string $inviteeContact,
        ?string $referrerContact = null,
    ): ReferralInviteCreationResult {
        if (! $referrer->exists || $referrer->getKey() === null) {
            throw new InvalidArgumentException('A persisted referrer model is required.');
        }

        $normalizer = app(ReferralContactNormalizer::class);
        $normalizedContact = $normalizer->normalize($inviteeContact);
        $inviteeHash = hash('sha256', $normalizedContact);

        if ($referrerContact !== null && hash_equals($inviteeHash, $normalizer->hash($referrerContact))) {
            throw new LogicException('A referrer cannot invite their own contact.');
        }

        $tokenLength = (int) config('referrals.defaults.token_length', 32);

        if ($tokenLength < 16 || $tokenLength > 128) {
            throw new InvalidArgumentException('Referral invite token length must be between 16 and 128 characters.');
        }

        $rawToken = Str::random($tokenLength);
        $tokenHash = hash('sha256', $rawToken);
        $now = CarbonImmutable::now();
        $guard = app(ReferralProgramGuard::class);

        /** @var array{result: ReferralInviteCreationResult, created: bool} $created */
        $created = DB::transaction(function () use (
            $program,
            $referrer,
            $normalizedContact,
            $inviteeHash,
            $tokenHash,
            $rawToken,
            $now,
            $guard,
        ): array {
            $lockedProgram = $guard->program($program, true);

            if (! $lockedProgram->isActiveAt($now)) {
                throw new LogicException('Referral invites require an active program within its date window.');
            }

            $referrerType = $referrer->getMorphClass();
            $referrerId = (string) $referrer->getKey();
            $terminalStatuses = [
                ReferralInviteStatus::Rewarded->value,
                ReferralInviteStatus::Expired->value,
                ReferralInviteStatus::Revoked->value,
            ];

            $existing = ReferralInvite::query()
                ->where('program_id', $lockedProgram->getKey())
                ->where('invitee_hash', $inviteeHash)
                ->whereNotIn('status', $terminalStatuses)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof ReferralInvite && $existing->isExpiredAt($now)) {
                $existing->status = ReferralInviteStatus::Expired;
                $existing->save();
                $existing = null;
            }

            if ($existing instanceof ReferralInvite) {
                if ($existing->referrer_type === $referrerType && (string) $existing->referrer_id === $referrerId) {
                    $token = null;

                    if ($existing->status === ReferralInviteStatus::Pending) {
                        $existing->rotateTokenHash($tokenHash);
                        $token = $rawToken;
                    }

                    return [
                        'result' => new ReferralInviteCreationResult($existing, $token),
                        'created' => false,
                    ];
                }

                throw ReferralAlreadyClaimedException::forInvitee();
            }

            if ($lockedProgram->max_invites_per_referrer !== null) {
                $inviteCount = ReferralInvite::query()
                    ->where('program_id', $lockedProgram->getKey())
                    ->where('referrer_type', $referrerType)
                    ->where('referrer_id', $referrerId)
                    ->whereNotIn('status', $terminalStatuses)
                    ->count();

                if ($inviteCount >= $lockedProgram->max_invites_per_referrer) {
                    throw new LogicException('The referrer has reached the program invite cap.');
                }
            }

            $expiryDays = $lockedProgram->invite_expiry_days ?? (int) config('referrals.defaults.invite_expiry_days', 30);

            if ($expiryDays < 1) {
                throw new InvalidArgumentException('Referral invite expiry must be at least one day.');
            }

            $invite = new ReferralInvite;
            $invite->forceFill([
                'program_id' => $lockedProgram->getKey(),
                'referrer_type' => $referrerType,
                'referrer_id' => $referrerId,
                'invitee_hash' => $inviteeHash,
                'invitee_contact_encrypted' => $normalizedContact,
                'token_hash' => $tokenHash,
                'status' => ReferralInviteStatus::Pending,
                'expired_at' => $now->addDays($expiryDays),
            ]);
            $invite->save();

            return [
                'result' => new ReferralInviteCreationResult($invite, $rawToken),
                'created' => true,
            ];
        });

        if ($created['created']) {
            ReferralInviteCreated::dispatch($created['result']->invite->fresh());
        }

        return $created['result'];
    }
}
