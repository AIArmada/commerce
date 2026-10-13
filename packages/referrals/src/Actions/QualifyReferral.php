<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\Referrals\Data\ReferralQualificationResult;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Enums\ReferralRewardSide;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Events\ReferralQualified;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Models\ReferralReward;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use AIArmada\Referrals\Support\ReferralRewardSpecValidator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

final class QualifyReferral
{
    use AsAction;

    public function handle(
        ReferralInvite | string $inviteOrToken,
        string $businessKey,
        ?CarbonImmutable $occurredAt = null,
    ): ReferralQualificationResult {
        $businessKey = mb_trim($businessKey);

        if ($businessKey === '' || mb_strlen($businessKey) > 191) {
            throw new InvalidArgumentException('Referral qualification business keys must contain 1 to 191 characters.');
        }

        $at = ($occurredAt ?? CarbonImmutable::now())->toImmutable();
        $guard = app(ReferralProgramGuard::class);
        $inviteId = $inviteOrToken instanceof ReferralInvite
            ? (string) $inviteOrToken->getKey()
            : null;

        $result = DB::transaction(function () use ($inviteOrToken, $businessKey, $at, $guard, $inviteId): array {
            $resolvedInvite = $inviteId !== null
                ? $guard->invite($inviteId)
                : $guard->inviteFromIdOrToken((string) $inviteOrToken);
            $program = $guard->program((string) $resolvedInvite->program_id, true);
            $invite = ReferralInvite::query()
                ->where('program_id', $program->getKey())
                ->whereKey($resolvedInvite->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($invite->status, [ReferralInviteStatus::Qualified, ReferralInviteStatus::Rewarded], true)) {
                return [
                    'result' => $this->storedResult($invite),
                    'qualified' => false,
                ];
            }

            if ($invite->status !== ReferralInviteStatus::Accepted) {
                throw new LogicException('Only accepted referral invites can qualify.');
            }

            if ($invite->isExpiredAt($at)) {
                throw new LogicException('An expired referral invite cannot qualify.');
            }

            if ($invite->accepted_at === null || $at->lessThan($invite->accepted_at)) {
                throw new LogicException('A referral cannot qualify before the invite was accepted.');
            }

            $acceptedBy = $invite->acceptedBy;

            if (! $acceptedBy instanceof Model
                || $acceptedBy->getMorphClass() !== $invite->accepted_by_type
                || (string) $acceptedBy->getKey() !== (string) $invite->accepted_by_id) {
                throw new LogicException('The accepted invitee identity is no longer available.');
            }

            $usedBusinessKey = ReferralInvite::query()
                ->where('qualification_business_key', $businessKey)
                ->where('id', '!=', $invite->getKey())
                ->exists();

            if ($usedBusinessKey) {
                throw new LogicException('The qualification business key is already assigned to another referral.');
            }

            if ($program->max_qualified_per_referrer_per_month !== null) {
                $monthStart = $at->startOfMonth();
                $monthEnd = $at->endOfMonth();
                $qualifiedCount = ReferralInvite::query()
                    ->where('program_id', $program->getKey())
                    ->where('referrer_type', $invite->referrer_type)
                    ->where('referrer_id', $invite->referrer_id)
                    ->whereIn('status', [ReferralInviteStatus::Qualified->value, ReferralInviteStatus::Rewarded->value])
                    ->whereBetween('qualified_at', [$monthStart, $monthEnd])
                    ->count();

                if ($qualifiedCount >= $program->max_qualified_per_referrer_per_month) {
                    throw new LogicException('The referrer has reached the monthly qualification cap.');
                }
            }

            $rewards = [];
            $referrerSpec = app(ReferralRewardSpecValidator::class)
                ->validate($program->referrer_reward, $program->currency);
            $refereeSpec = app(ReferralRewardSpecValidator::class)
                ->validate($program->referee_reward, $program->currency);

            if ($referrerSpec !== null) {
                $rewards[] = $this->createReward(
                    $invite,
                    $program,
                    ReferralRewardSide::Referrer,
                    $invite->referrer_type,
                    $invite->referrer_id,
                    $referrerSpec,
                );
            }

            if ($refereeSpec !== null) {
                $rewards[] = $this->createReward(
                    $invite,
                    $program,
                    ReferralRewardSide::Referee,
                    $invite->accepted_by_type,
                    $invite->accepted_by_id,
                    $refereeSpec,
                );
            }

            $invite->forceFill([
                'qualification_business_key' => $businessKey,
                'qualified_at' => $at,
                'status' => ReferralInviteStatus::Qualified,
            ]);
            $invite->save();

            return [
                'result' => new ReferralQualificationResult($invite, $rewards),
                'qualified' => true,
            ];
        });

        if ($result['qualified']) {
            ReferralQualified::dispatch($result['result']->invite->fresh(), $result['result']->rewards);
        }

        return $result['result'];
    }

    private function createReward(
        ReferralInvite $invite,
        ReferralProgram $program,
        ReferralRewardSide $side,
        ?string $beneficiaryType,
        ?string $beneficiaryId,
        array $spec,
    ): ReferralReward {
        if ($beneficiaryType === null || $beneficiaryId === null) {
            throw new LogicException(sprintf('The %s reward requires a beneficiary morph.', $side->value));
        }

        $reward = new ReferralReward;
        $reward->forceFill([
            'invite_id' => $invite->getKey(),
            'program_id' => $program->getKey(),
            'side' => $side,
            'beneficiary_type' => $beneficiaryType,
            'beneficiary_id' => $beneficiaryId,
            'driver' => $spec['driver'],
            'amount_minor' => $spec['amount_minor'] ?? null,
            'points' => $spec['points'] ?? null,
            'currency' => $spec['currency'] ?? (($spec['amount_minor'] ?? null) !== null ? $program->currency : null),
            'spec' => $spec,
            'status' => ReferralRewardStatus::Pending,
            'idempotency_key' => hash('sha256', 'referrals:reward:' . $invite->getKey() . ':' . $side->value),
        ]);
        $reward->save();

        return $reward;
    }

    private function storedResult(ReferralInvite $invite): ReferralQualificationResult
    {
        /** @var list<ReferralReward> $rewards */
        $rewards = $invite->rewards()->orderBy('side')->get()->all();

        return new ReferralQualificationResult($invite, $rewards);
    }
}
