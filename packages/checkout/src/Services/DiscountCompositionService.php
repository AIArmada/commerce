<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Services;

use AIArmada\Checkout\Contracts\DiscountProvider;
use AIArmada\Checkout\Data\DiscountCommitment;
use AIArmada\Checkout\Data\DiscountProposal;
use AIArmada\Checkout\Models\CheckoutSession;

final class DiscountCompositionService
{
    public function __construct(
        /** @var array<int, DiscountProvider> */
        private readonly array $providers = [],
    ) {}

    /**
     * @return array{proposals: array<DiscountProposal>, allocations: array<int, DiscountProposal>, totalDiscount: int}
     */
    public function evaluate(CheckoutSession $session, array $discountData): array
    {
        $proposals = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->evaluate($session, $discountData) as $proposal) {
                $proposals[] = $proposal;
            }
        }

        usort(
            $proposals,
            fn (DiscountProposal $a, DiscountProposal $b) => $b->priority <=> $a->priority
            ?: strcasecmp($a->providerKey, $b->providerKey)
            ?: strcasecmp($a->candidateKey, $b->candidateKey)
        );

        $eligibleSubtotal = max(0, (int) $session->subtotal);
        $remainingCap = $eligibleSubtotal;
        $allocated = [];
        $totalDiscount = 0;

        foreach ($proposals as $proposal) {
            if ($remainingCap <= 0) {
                break;
            }

            $allocate = min($proposal->requestedAmount, $remainingCap);

            if ($allocate <= 0) {
                continue;
            }

            $allocated[] = $proposal->withAllocatedAmount($allocate);
            $remainingCap -= $allocate;
            $totalDiscount += $allocate;
        }

        $totalDiscount = min($totalDiscount, $eligibleSubtotal);

        return [
            'proposals' => $proposals,
            'allocations' => $allocated,
            'totalDiscount' => $totalDiscount,
        ];
    }

    /**
     * @param  array<int, DiscountProposal>  $accepted
     * @return array<string, DiscountCommitment>
     */
    public function commit(CheckoutSession $session, array $accepted): array
    {
        if ($this->providers === []) {
            return [];
        }

        $commitments = [];

        foreach ($this->providers as $provider) {
            $providerAccepted = array_values(array_filter(
                $accepted,
                fn (DiscountProposal $p) => $p->providerKey === $provider->providerKey(),
            ));

            if ($providerAccepted === []) {
                continue;
            }

            foreach ($provider->commit($session, $providerAccepted) as $key => $commitment) {
                $commitments[$key] = $commitment;
            }
        }

        return $commitments;
    }

    /**
     * @param  array<string, DiscountCommitment>  $commitments
     */
    public function release(CheckoutSession $session, array $commitments): void
    {
        foreach ($this->providers as $provider) {
            $providerCommitments = array_filter(
                $commitments,
                fn (DiscountCommitment $c) => $c->providerKey === $provider->providerKey(),
            );

            if ($providerCommitments === []) {
                continue;
            }

            $provider->release($session, $providerCommitments);
        }
    }
}
