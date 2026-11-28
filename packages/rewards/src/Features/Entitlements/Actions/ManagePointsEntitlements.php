<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Data\RewardBeneficiaryRole;
use AIArmada\Rewards\Data\RewardComponent;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Features\Entitlements\Api\RewardPointsEntitlementApi;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAward;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use RuntimeException;

final readonly class ManagePointsEntitlements implements RewardPointsEntitlementApi
{
    public function __construct(
        private RewardSourceFactReader $reader,
        private EvaluateRewardEntitlementAction $evaluate,
    ) {}

    public function connectionName(): string
    {
        return (new RewardAward)->getConnection()->getName();
    }

    public function evaluatePoints(string $businessKey, string $recipientType, string $recipientId, string $role, ?string $familyKey, ?int $expectedPoints): void
    {
        $beneficiaryRole = RewardBeneficiaryRole::from($role);

        (new RewardAward)->getConnection()->transaction(function () use ($businessKey, $recipientType, $recipientId, $beneficiaryRole, $familyKey, $expectedPoints): void {
            (new EvaluateRewardSourceAction($businessKey, $recipientId, $beneficiaryRole, $recipientType, $familyKey))->handle($this->reader, $this->evaluate);

            $awards = $this->livePoints($businessKey, $recipientType, $recipientId, $beneficiaryRole)
                ->when($familyKey !== null, static fn (Builder $query): Builder => $query->where('family_key', $familyKey))
                ->orderBy('id')
                ->limit(2)
                ->get();

            if ($awards->count() !== 1) {
                throw new RuntimeException('Rewards evaluation must produce exactly one live points award for the recipient scope.');
            }

            $award = $awards->firstOrFail();
            $awardedPoints = $award->amount_snapshot['points'] ?? null;

            if ($expectedPoints === null || ! is_int($awardedPoints) || $awardedPoints !== $expectedPoints) {
                throw new RuntimeException('The rewards award points amount does not match the expected points.');
            }
        });
    }

    public function reversePoints(string $businessKey, string $recipientType, string $recipientId, string $role, string $reason, array $evidence): void
    {
        $beneficiaryRole = RewardBeneficiaryRole::from($role);

        (new RewardAward)->getConnection()->transaction(function () use ($businessKey, $recipientType, $recipientId, $beneficiaryRole, $reason, $evidence): void {
            $ids = $this->livePoints($businessKey, $recipientType, $recipientId, $beneficiaryRole)->pluck('id')->all();

            if ($ids === []) {
                return;
            }

            $report = ApplyRewardSourceCorrectionsAction::run('system', null, 'system', null, 200, [
                ['business_key' => $businessKey, 'reason' => $reason, 'evidence' => $evidence],
            ]);

            foreach ($ids as $id) {
                if (array_key_exists((string) $id, $report['errors'])) {
                    throw new RuntimeException('The rewards corrections sweep reported an error for the points award.');
                }
            }

            if ($this->livePoints($businessKey, $recipientType, $recipientId, $beneficiaryRole)->exists()) {
                throw new RuntimeException('The rewards corrections sweep left a live points award.');
            }
        });
    }

    /** @return Builder<RewardAward> */
    private function livePoints(string $businessKey, string $recipientType, string $recipientId, RewardBeneficiaryRole $role): Builder
    {
        $query = RewardAward::query()
            ->where('source_business_key', $businessKey)
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->where('component', RewardComponent::Points)
            ->where('beneficiary_role', $role)
            ->whereIn('status', [RewardAwardStatus::Held, RewardAwardStatus::PendingReview, RewardAwardStatus::Approved, RewardAwardStatus::Fulfilled]);

        if (OwnerContext::hasOverride() || OwnerContext::resolve() !== null) {
            $programs = [];

            foreach ((clone $query)->distinct()->pluck('program_id') as $programId) {
                try {
                    app(RewardProgramLockApi::class)->assertProgramInScope((string) $programId);
                    $programs[] = (string) $programId;
                } catch (AuthorizationException | InvalidArgumentException) {
                    continue;
                }
            }

            $query->whereIn('program_id', $programs);
        }

        return $query;
    }
}
