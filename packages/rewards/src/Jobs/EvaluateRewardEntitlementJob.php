<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Jobs;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Actions\RecordRewardMilestoneContributionAction;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardEvaluationOutcome;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardProgramVersion;
use AIArmada\Rewards\Support\RewardEvaluationCandidates;
use AIArmada\Rewards\Support\RewardProgramGuard;
use AIArmada\Rewards\Support\RewardSourceFact;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Evaluates one committed source fact against every active program.
 *
 * Dispatched after commit by the share receipt/correction/claim writers.
 * Unique-until-processing per business key collapses duplicate dispatches
 * while still allowing genuine re-evaluation after claim or correction.
 * Transient kernel refusals (`Refused: ...` — no enrollment, unclaimed
 * guest, paused program, cap race losers) are skipped per candidate; any
 * other failure throws and retries. Queue workers resolve no ambient
 * owner, so the program scan widens and each candidate (and its milestone
 * recording) runs under the owning program's own owner context. An owner
 * that is already resolved keeps the scan scoped to it, so a synchronous
 * in-tenant dispatch cannot reach another tenant's programs. Awarded facts
 * additionally record milestone contributions declared by the applicable
 * version.
 *
 * Callers may narrow the evaluation to one (recipient, role, family)
 * tuple — the scoped form used by the referrals points rail, where one
 * frozen side of one business fact must be evaluated in isolation. A
 * scoped dispatch takes part in its own uniqueness window so queued
 * scoped and unscoped evaluations of the same fact never collapse into
 * each other.
 */
final class EvaluateRewardEntitlementJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $businessKey,
        public readonly ?string $onlyRecipientId = null,
        public readonly ?RewardBeneficiaryRole $onlyRole = null,
        public readonly ?string $onlyRecipientType = null,
        public readonly ?string $onlyFamilyKey = null,
    ) {}

    public function uniqueId(): string
    {
        return 'reward-eval:' . $this->businessKey . $this->scopeSuffix();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(RewardSourceFactReader $reader, EvaluateRewardEntitlementAction $evaluate): void
    {
        $fact = $reader->read($this->businessKey);

        if (! $fact instanceof RewardSourceFact) {
            return;
        }

        $now = CarbonImmutable::now('UTC');
        $recipientType = $this->onlyRecipientType ?? $this->recipientType();

        // System enumeration only when nothing ambient scopes the run: the
        // queue worker and the console resolve no owner, so the scan widens
        // past the ambient scope and every candidate is then evaluated under
        // its own program's owner. When an owner IS resolved — including an
        // explicit-global context — the scan stays scoped exactly as it was,
        // so a synchronous in-tenant dispatch (the referrals points rail)
        // can never reach another tenant's programs.
        $systemEnumeration = ! OwnerContext::hasOverride() && OwnerContext::resolve() === null;

        foreach (RewardEvaluationCandidates::for($fact, $now, systemEnumeration: $systemEnumeration) as $candidate) {
            if (! $this->inScope($candidate)) {
                continue;
            }

            try {
                RewardProgramGuard::withProgramOwner($candidate['program'], function () use ($evaluate, $fact, $candidate, $now, $recipientType): void {
                    $result = $evaluate->handle(
                        $candidate['program'],
                        $candidate['component'],
                        $recipientType,
                        $candidate['recipient_id'],
                        $candidate['role'],
                        $this->businessKey,
                        $now,
                        'system',
                        null,
                        'system',
                    );

                    if ($result->outcome === RewardEvaluationOutcome::Awarded) {
                        $this->recordMilestones($fact, $candidate['version']->rules, $candidate['recipient_id'], $candidate['role']->value, (string) $candidate['program']->getKey(), $candidate['program']->family_key, $recipientType);
                    }
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
     * @param  array{program: RewardProgram, version: RewardProgramVersion, component: RewardComponent, role: RewardBeneficiaryRole, recipient_id: string}  $candidate
     */
    private function inScope(array $candidate): bool
    {
        return ($this->onlyRecipientId === null || $candidate['recipient_id'] === $this->onlyRecipientId)
            && ($this->onlyRole === null || $candidate['role'] === $this->onlyRole)
            && ($this->onlyFamilyKey === null || $candidate['program']->family_key === $this->onlyFamilyKey);
    }

    private function scopeSuffix(): string
    {
        if ($this->onlyRecipientId === null && $this->onlyRole === null && $this->onlyRecipientType === null && $this->onlyFamilyKey === null) {
            return '';
        }

        return ':' . implode('|', [
            $this->onlyRecipientType ?? '',
            $this->onlyRecipientId ?? '',
            $this->onlyRole?->value ?? '',
            $this->onlyFamilyKey ?? '',
        ]);
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
     * @param  array<string, mixed>  $rules
     */
    private function recordMilestones(
        RewardSourceFact $fact,
        mixed $rules,
        string $recipientId,
        string $role,
        string $programId,
        string $familyKey,
        string $recipientType,
    ): void {
        $milestones = is_array($rules) && is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [];

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
                RecordRewardMilestoneContributionAction::run(
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
                // The fact became unreadable mid-flight; validation errors
                // below indicate version-data bugs and must throw instead.
                if (str_starts_with($exception->getMessage(), 'Refused: unknown_source')) {
                    continue;
                }

                throw $exception;
            }
        }
    }
}
