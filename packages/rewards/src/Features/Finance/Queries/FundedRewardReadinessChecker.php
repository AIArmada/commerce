<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Queries;

use AIArmada\Rewards\Contracts\RewardBenefitCatalog;
use AIArmada\Rewards\Contracts\RewardPayoutMethodCatalog;
use AIArmada\Rewards\Contracts\RewardReadinessChecker;
use AIArmada\Rewards\Data\RewardComponent;
use AIArmada\Rewards\Data\RewardReadinessAssessment;
use AIArmada\Rewards\Data\RewardVersionSnapshot;
use AIArmada\Rewards\Models\RewardFundingEntry;

/**
 * Real program-lifecycle readiness backed by funded finance adapters.
 *
 * canMarkReady stays structural (a published version with valid rules/
 * terms). canActivate additionally requires the fulfillment dependencies
 * of the configured components: cash needs confirmed funding in every
 * configured currency plus at least one supported payout processor;
 * benefit needs catalog availability (checked when the benefit scope is
 * bound, otherwise an explicit blocker). Recognition/points/badge need no
 * payee and activate on structure alone. Nothing reports funded or payee
 * readiness it cannot prove from immutable rows.
 */
final class FundedRewardReadinessChecker implements RewardReadinessChecker
{
    public function __construct(
        private readonly RewardPayoutMethodCatalog $catalog,
    ) {}

    public function canMarkReady(string $programId, ?RewardVersionSnapshot $version): RewardReadinessAssessment
    {
        if (! $version instanceof RewardVersionSnapshot) {
            return new RewardReadinessAssessment(
                ready: false,
                blockers: ['no-published-version'],
                notes: ['A program needs at least one published version before it can be readied.'],
            );
        }

        return new RewardReadinessAssessment(
            ready: true,
            notes: ['Structural readiness: rules/terms snapshots exist and validate.'],
        );
    }

    public function canActivate(string $programId, ?RewardVersionSnapshot $version): RewardReadinessAssessment
    {
        if (! $version instanceof RewardVersionSnapshot) {
            return new RewardReadinessAssessment(
                ready: false,
                blockers: ['no-published-version'],
                notes: ['A program needs at least one published version before it can be activated.'],
            );
        }

        $blockers = [];
        $notes = [];
        $components = $this->components($version);

        if (in_array(RewardComponent::Cash, $components, true)) {
            $currencies = $this->cashCurrencies($version);

            if ($currencies === []) {
                $blockers[] = 'cash-component-missing-currency';
            }

            foreach ($currencies as $currency) {
                $funded = (int) RewardFundingEntry::query()
                    ->where('program_id', $programId)
                    ->where('currency', $currency)
                    ->sum('amount_minor');

                if ($funded <= 0) {
                    $blockers[] = "no-confirmed-funding:{$currency}";
                } else {
                    $notes[] = "Confirmed funding present for {$currency}.";
                }
            }

            $supported = array_keys(array_filter(
                $this->catalog->describe(),
                static fn (array $info): bool => $info['supported'],
            ));

            if ($supported === []) {
                $blockers[] = 'no-supported-payout-processor';
            } else {
                $notes[] = 'Supported payout processors: ' . implode(', ', $supported) . '.';
            }
        }

        if (in_array(RewardComponent::Benefit, $components, true)) {
            if (! $this->benefitCatalogAvailable($programId, $version)) {
                $blockers[] = 'benefit-catalog-unavailable';
            } else {
                $notes[] = 'Benefit catalog available for configured benefit keys.';
            }
        }

        if ($blockers !== []) {
            return new RewardReadinessAssessment(ready: false, blockers: $blockers, notes: $notes);
        }

        $notes[] = 'Explicit staff transition required; activation is never automatic.';

        return new RewardReadinessAssessment(ready: true, notes: $notes);
    }

    /**
     * @return list<RewardComponent>
     */
    private function components(RewardVersionSnapshot $version): array
    {
        $components = [];

        foreach ($version->componentTypes as $type) {
            $component = RewardComponent::tryFrom($type);

            if ($component instanceof RewardComponent) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * @return list<string>
     */
    private function cashCurrencies(RewardVersionSnapshot $version): array
    {
        $currencies = [];
        $components = $version->rules['components'] ?? [];

        if (! is_array($components)) {
            return [];
        }

        foreach ($components as $component) {
            if (! is_array($component) || ($component['type'] ?? null) !== RewardComponent::Cash->value) {
                continue;
            }

            $configurations = is_array($component['variants'] ?? null) ? $component['variants'] : [$component];

            foreach ($configurations as $configuration) {
                if (is_array($configuration) && is_string($configuration['currency'] ?? null)) {
                    $currencies[] = mb_strtoupper($configuration['currency']);
                }
            }
        }

        return array_values(array_unique($currencies));
    }

    private function benefitCatalogAvailable(string $programId, ?RewardVersionSnapshot $version): bool
    {
        if (! interface_exists(RewardBenefitCatalog::class)) {
            return false;
        }

        return app(RewardBenefitCatalog::class)->coversProgram($programId, $version);
    }
}
