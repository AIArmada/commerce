<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Enums\RewardVerificationLevel;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardProgramVersion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Automation candidate resolution for single-fact evaluation.
 *
 * For one committed source fact, resolves every (active program, enabled
 * component, bound role, recipient) tuple the evaluate job must attempt.
 * Binding follows the kernel exactly: sharer-side roles bind the fact's
 * sharer id, actor-side roles bind the fact's actor id. Invalid and
 * cancelled facts yield no candidates — automation stays silent instead
 * of freezing rejection rows for facts that could never qualify;
 * policy-merit rejections still freeze through explicit calls.
 * Unattributed facts drop only the sharer side: sharer-bound roles need
 * attributed evidence, while actor-bound roles may earn the actor's own
 * unattributed act.
 */
final class RewardEvaluationCandidates
{
    /**
     * @param  bool  $systemEnumeration  System context (queued job): widen the
     *                                   program scan past the ambient owner scope — every candidate is then
     *                                   evaluated under its own program owner by the caller.
     * @return list<array{
     *     program: RewardProgram,
     *     version: RewardProgramVersion,
     *     components: list<RewardComponent>,
     *     milestones: list<array<string, mixed>>,
     *     role: RewardBeneficiaryRole,
     *     recipient_id: string,
     * }>
     */
    public static function for(RewardSourceFact $fact, CarbonImmutable $at, bool $systemEnumeration = false): array
    {
        if (! $fact->isValid || $fact->isCancelled) {
            return [];
        }

        $sides = [];

        if ($fact->sharerId !== null && $fact->isAttributed) {
            $sides[] = ['role' => RewardBeneficiaryRole::Sharer, 'recipient_id' => $fact->sharerId];
        }

        if ($fact->actorId !== null) {
            $sides[] = [
                'role' => RewardBeneficiaryRole::actorSide($fact->actorRole),
                'recipient_id' => $fact->actorId,
            ];
        }

        if ($sides === []) {
            return [];
        }

        $at = $at->setTimezone('UTC');
        $candidates = [];

        // System enumeration (the queued job) runs without an ambient owner,
        // so the program scan widens past the current owner scope on
        // purpose: each candidate is later evaluated under its own program
        // owner via RewardProgramGuard::withProgramOwner(). This is the
        // only sanctioned unscoped query on programs.
        $programs = RewardProgram::query()
            ->when(
                $systemEnumeration && config('rewards.owner.enabled', true),
                static fn (Builder $query): Builder => $query->withoutGlobalScopes(),
            )
            ->where('status', RewardProgramStatus::Active->value)
            ->orderBy('family_key')
            ->get();

        foreach ($programs as $program) {
            /** @var RewardProgram $program */
            $versions = RewardProgramVersion::query()
                ->where('program_id', $program->getKey())
                ->orderBy('version')
                ->get();

            $timeline = [];

            foreach ($versions as $version) {
                /** @var RewardProgramVersion $version */
                $timeline[] = [
                    'id' => (string) $version->getKey(),
                    'version' => (int) $version->version,
                    'effective_from' => CarbonImmutable::parse($version->effective_from)->setTimezone('UTC'),
                ];
            }

            $applicable = RewardVersionResolver::resolveAt($timeline, $at);

            if ($applicable === null) {
                continue;
            }

            $resolved = null;

            foreach ($versions as $version) {
                if ((string) $version->getKey() === $applicable['id']) {
                    $resolved = $version;

                    break;
                }
            }

            if (! $resolved instanceof RewardProgramVersion) {
                continue;
            }

            $rules = $resolved->rules;
            $entries = is_array($rules['components'] ?? null) ? $rules['components'] : [];

            foreach ($sides as $side) {
                $components = [];

                foreach ($entries as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $component = RewardComponent::tryFrom((string) ($entry['type'] ?? ''));

                    if (! $component instanceof RewardComponent) {
                        continue;
                    }

                    $variants = $entry['variants'] ?? null;

                    if (is_array($variants)) {
                        $matches = false;

                        foreach ($variants as $variant) {
                            if (is_array($variant)
                                && in_array($fact->outcome, $variant['outcomes'] ?? [], true)
                                && in_array($side['role']->value, $variant['roles'] ?? [], true)) {
                                $matches = true;

                                break;
                            }
                        }

                        if ($matches) {
                            $components[$component->value] = $component;
                        }

                        continue;
                    }

                    if (in_array($fact->outcome, $rules['eligibility']['outcomes'] ?? [], true)) {
                        $components[$component->value] = $component;
                    }
                }

                $milestones = [];

                foreach (is_array($rules['milestones'] ?? null) ? $rules['milestones'] : [] as $milestone) {
                    if (! is_array($milestone)
                        || ! in_array($fact->outcome, $milestone['outcomes'] ?? [], true)
                        || ! in_array($side['role']->value, $milestone['roles'] ?? [], true)) {
                        continue;
                    }

                    $minimum = RewardVerificationLevel::tryFrom((string) ($milestone['min_verification'] ?? ''));

                    if (! $minimum instanceof RewardVerificationLevel
                        || ! $fact->verification->satisfiesFor($minimum, $fact->outcome)
                        || (($milestone['first_only'] ?? false) && ! $fact->isFirstTime)) {
                        continue;
                    }

                    $milestones[] = $milestone;
                }

                if ($components === [] && $milestones === []) {
                    continue;
                }

                $candidates[] = [
                    'program' => $program,
                    'version' => $resolved,
                    'components' => array_values($components),
                    'milestones' => $milestones,
                    'role' => $side['role'],
                    'recipient_id' => $side['recipient_id'],
                ];
            }
        }

        return $candidates;
    }
}
