<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\PolicyCombiningAlgorithm;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;
use AIArmada\FilamentAuthz\Models\AccessPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class PolicyEngine
{
    protected const CACHE_KEY_PREFIX = 'permissions:policies:';

    protected PolicyCombiningAlgorithm $combiningAlgorithm;

    public function __construct()
    {
        $this->combiningAlgorithm = PolicyCombiningAlgorithm::from(
            config('filament-authz.policies.combining_algorithm', 'deny-overrides')
        );
    }

    /**
     * Evaluate policies for an action/resource with context.
     *
     * @param  array<string, mixed>  $context
     */
    public function evaluate(string $action, string $resource, array $context = []): PolicyDecision
    {
        $policies = $this->getApplicablePolicies($action, $resource);

        if ($policies->isEmpty()) {
            return PolicyDecision::NotApplicable;
        }

        $decisions = [];

        foreach ($policies as $policy) {
            $decision = $this->evaluatePolicy($policy, $context);
            $decisions[] = $decision;
        }

        return $this->combiningAlgorithm->combine($decisions);
    }

    /**
     * Check if an action is permitted.
     *
     * @param  array<string, mixed>  $context
     */
    public function isPermitted(string $action, string $resource, array $context = []): bool
    {
        $decision = $this->evaluate($action, $resource, $context);

        return $decision === PolicyDecision::Permit;
    }

    /**
     * Check if an action is denied.
     *
     * @param  array<string, mixed>  $context
     */
    public function isDenied(string $action, string $resource, array $context = []): bool
    {
        $decision = $this->evaluate($action, $resource, $context);

        return $decision === PolicyDecision::Deny;
    }

    /**
     * Evaluate a single policy against context.
     *
     * @param  array<string, mixed>  $context
     */
    public function evaluatePolicy(AccessPolicy $policy, array $context): PolicyDecision
    {
        // Check if policy is active
        if (! $policy->is_active) {
            return PolicyDecision::NotApplicable;
        }

        // Check temporal validity
        if ($policy->valid_from !== null && now()->lt($policy->valid_from)) {
            return PolicyDecision::NotApplicable;
        }

        if ($policy->valid_until !== null && now()->gt($policy->valid_until)) {
            return PolicyDecision::NotApplicable;
        }

        // Evaluate conditions
        if (! $policy->evaluateConditions($context)) {
            return PolicyDecision::NotApplicable;
        }

        // Return the policy effect as a decision
        return $policy->getEffectEnum() === PolicyEffect::Allow
            ? PolicyDecision::Permit
            : PolicyDecision::Deny;
    }

    /**
     * Get all policies applicable to an action/resource.
     *
     * @return Collection<int, AccessPolicy>
     */
    public function getApplicablePolicies(string $action, string $resource): Collection
    {
        $cacheKey = self::CACHE_KEY_PREFIX . "applicable:{$action}:{$resource}";
        $ttl = config('filament-authz.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($action, $resource): Collection {
            return AccessPolicy::query()
                ->where('is_active', true)
                ->where(function ($query) use ($action): void {
                    $query->where('target_action', $action)
                        ->orWhere('target_action', '*');
                })
                ->where(function ($query) use ($resource): void {
                    $query->where('target_resource', $resource)
                        ->orWhere('target_resource', '*');
                })
                ->orderBy('priority', 'desc')
                ->get();
        });
    }

    /**
     * Set the combining algorithm.
     */
    public function setCombiningAlgorithm(PolicyCombiningAlgorithm $algorithm): self
    {
        $this->combiningAlgorithm = $algorithm;

        return $this;
    }

    /**
     * Get the current combining algorithm.
     */
    public function getCombiningAlgorithm(): PolicyCombiningAlgorithm
    {
        return $this->combiningAlgorithm;
    }

    /**
     * Get all active policies.
     *
     * @return Collection<int, AccessPolicy>
     */
    public function getActivePolicies(): Collection
    {
        return AccessPolicy::query()
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Get policies by effect.
     *
     * @return Collection<int, AccessPolicy>
     */
    public function getPoliciesByEffect(PolicyEffect $effect): Collection
    {
        return AccessPolicy::query()
            ->where('effect', $effect)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Explain why a decision was made.
     *
     * @param  array<string, mixed>  $context
     * @return array{decision: PolicyDecision, matching_policies: array<int, array{name: string, effect: string, priority: int}>, algorithm: string}
     */
    public function explain(string $action, string $resource, array $context = []): array
    {
        $policies = $this->getApplicablePolicies($action, $resource);
        $matchingPolicies = [];
        $decisions = [];

        foreach ($policies as $policy) {
            $decision = $this->evaluatePolicy($policy, $context);

            if ($decision !== PolicyDecision::NotApplicable) {
                $matchingPolicies[] = [
                    'name' => $policy->name,
                    'effect' => $policy->getEffectEnum()->value,
                    'priority' => $policy->priority,
                    'decision' => $decision->value,
                ];
                $decisions[] = $decision;
            }
        }

        $finalDecision = $policies->isEmpty()
            ? PolicyDecision::NotApplicable
            : $this->combiningAlgorithm->combine($decisions);

        return [
            'decision' => $finalDecision,
            'matching_policies' => $matchingPolicies,
            'algorithm' => $this->combiningAlgorithm->value,
        ];
    }

    /**
     * Clear the policy cache.
     */
    public function clearCache(): void
    {
        // Get all cached policy keys and clear them
        // In a real implementation, you might want to use cache tags
        $policies = AccessPolicy::all();

        foreach ($policies as $policy) {
            Cache::forget(self::CACHE_KEY_PREFIX . "applicable:{$policy->target_action}:{$policy->target_resource}");
        }
    }
}
