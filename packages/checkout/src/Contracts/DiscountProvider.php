<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use AIArmada\Checkout\Data\DiscountCommitment;
use AIArmada\Checkout\Data\DiscountProposal;
use AIArmada\Checkout\Models\CheckoutSession;

interface DiscountProvider
{
    public function providerKey(): string;

    /**
     * @param  array<string, mixed>  $discountData
     * @return array<int, DiscountProposal>
     */
    public function evaluate(CheckoutSession $session, array $discountData): array;

    /**
     * @param  array<int, DiscountProposal>  $accepted
     * @return array<string, DiscountCommitment>
     */
    public function commit(CheckoutSession $session, array $accepted): array;

    /**
     * @param  array<string, DiscountCommitment>  $commitments
     */
    public function release(CheckoutSession $session, array $commitments): void;
}
