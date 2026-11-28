<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Data\RewardBeneficiaryRole;
use AIArmada\Rewards\Data\RewardComponent;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardProgramVersion;
use AIArmada\Rewards\Support\RewardEvaluationCandidates;
use AIArmada\Rewards\Support\RewardProgramGuard;
use AIArmada\Rewards\Data\RewardSourceFact;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class EvaluateRewardSourceAction
{
    public function __construct(
        private string $businessKey,
        private ?string $onlyRecipientId = null,
        private ?RewardBeneficiaryRole $onlyRole = null,
        private ?string $onlyRecipientType = null,
        private ?string $onlyFamilyKey = null,
    ) {}

    public function handle(RewardSourceFactReader $reader, EvaluateRewardEntitlementAction $evaluate): void
    {
        $fact = $reader->read($this->businessKey);

        if (! $fact instanceof RewardSourceFact) {
            return;
        }

        $now = CarbonImmutable::now('UTC');
        $recipientType = $this->onlyRecipientType ?? $this->recipientType();

        $systemEnumeration = ! OwnerContext::hasOverride() && OwnerContext::resolve() === null;

        foreach (RewardEvaluationCandidates::for($fact, $now, systemEnumeration: $systemEnumeration) as $candidate) {
            if (! $this->inScope($candidate)) {
                continue;
            }

            try {
                RewardProgramGuard::withProgramOwner($candidate['program'], function () use ($evaluate, $fact, $candidate, $now, $recipientType, $reader): void {
                    foreach ($candidate['components'] as $component) {
                        try {
                            $evaluate->handle(
                                $candidate['program'],
                                $component,
                                $recipientType,
                                $candidate['recipient_id'],
                                $candidate['role'],
                                $this->businessKey,
                                $now,
                                'system',
                                null,
                                'system',
                            );
                        } catch (InvalidArgumentException $exception) {
                            if (! str_starts_with($exception->getMessage(), 'Refused:')) {
                                throw $exception;
                            }
                        }
                    }

                    $this->recordMilestones($fact, $candidate['milestones'], $candidate['recipient_id'], $candidate['role']->value, (string) $candidate['program']->getKey(), $candidate['program']->family_key, $recipientType, $reader);
                });
            } catch (InvalidArgumentException $exception) {
                if (str_starts_with($exception->getMessage(), 'Refused:')) {
                    continue;
                }

                throw $exception;
            }
        }
    }

    /**
     * @param  array{program: RewardProgram, version: RewardProgramVersion, components: list<RewardComponent>, milestones: list<array<string, mixed>>, role: RewardBeneficiaryRole, recipient_id: string}  $candidate
     */
    private function inScope(array $candidate): bool
    {
        return ($this->onlyRecipientId === null || $candidate['recipient_id'] === $this->onlyRecipientId)
            && ($this->onlyRole === null || $candidate['role'] === $this->onlyRole)
            && ($this->onlyFamilyKey === null || $candidate['program']->family_key === $this->onlyFamilyKey);
    }

    private function recipientType(): string
    {
        $types = config('rewards.recipient_types', []);

        if (! is_array($types) || ! is_string($types[0] ?? null) || $types[0] === '') {
            throw new InvalidArgumentException('Refused: rewards.recipient_types registers no recipient type.');
        }

        return $types[0];
    }

    /**
     * @param  list<array<string, mixed>>  $milestones
     */
    private function recordMilestones(
        RewardSourceFact $fact,
        array $milestones,
        string $recipientId,
        string $role,
        string $programId,
        string $familyKey,
        string $recipientType,
        RewardSourceFactReader $reader,
    ): void {
        $milestoneAction = new RecordRewardMilestoneContributionAction($reader);

        foreach ($milestones as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $component = RewardComponent::tryFrom((string) ($definition['reward']['type'] ?? ''));
            $key = $definition['key'] ?? null;
            $target = $definition['target_count'] ?? null;
            $window = $definition['window_days'] ?? null;

            if (! $component instanceof RewardComponent || ! is_string($key) || ! is_int($target) || ! is_int($window)) {
                continue;
            }

            try {
                $milestoneAction->handle(
                    $recipientType,
                    $recipientId,
                    $familyKey,
                    $key,
                    $target,
                    $window,
                    $component,
                    is_array($definition['reward']) ? $definition['reward'] : [],
                    $this->businessKey,
                    $recipientType,
                    $recipientId,
                    $role,
                    $fact->outcome,
                    $fact->occurredAt,
                    $programId,
                    $fact->snapshot(),
                );
            } catch (InvalidArgumentException $exception) {
                if (str_starts_with($exception->getMessage(), 'Refused:')) {
                    continue;
                }

                throw $exception;
            }
        }
    }
}
