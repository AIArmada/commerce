<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Billing;

use AIArmada\CashierChip\Invoice\Invoice;
use AIArmada\CashierChip\Payment\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Host-facing subscription behavior for configurable Eloquent billable relations.
 */
interface SubscriptionRecord
{
    public function valid(): bool;

    public function onTrial(): bool;

    public function hasExpiredTrial(): bool;

    public function trialEnd(): ?CarbonImmutable;

    public function hasProduct(string $product): bool;

    public function hasPrice(string $price): bool;

    public function hasIncompletePayment(): bool;

    public function charge(?int $amount = null): Payment;

    public function upcomingInvoice(): ?Invoice;

    /** @return Collection<int, Invoice> */
    public function invoices(?int $limit = 25): Collection;
}
