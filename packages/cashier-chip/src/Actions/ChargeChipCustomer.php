<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Actions;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Payment\Payment;
use AIArmada\Chip\Data\PurchaseData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Lorisleiva\Actions\Concerns\AsAction;
use SensitiveParameter;

final class ChargeChipCustomer
{
    use AsAction;

    /**
     * @param  Model&BillableContract  $billable
     * @param  array<string, mixed>  $options
     *
     * @throws IncompletePayment
     */
    public function handle(Model $billable, int $amount, #[SensitiveParameter] ?string $recurringToken = null, array $options = []): Payment
    {
        $rateLimitKey = 'cashier-chip:charge:' . ($billable->chipId() ?? $billable->getKey());
        $executed = RateLimiter::attempt(
            key: $rateLimitKey,
            maxAttempts: (int) config('cashier-chip.rate_limits.charges_per_minute', 30),
            callback: fn (): bool => true,
            decaySeconds: 60
        );

        if (! $executed) {
            throw new IncompletePayment(
                new Payment(PurchaseData::from(['id' => 'rate_limited', 'status' => 'failed'])),
                'Rate limit exceeded. Please wait before making another charge.'
            );
        }

        $metadata = $this->billableMetadata($billable, $options['metadata'] ?? null);

        $builder = Cashier::chip()->purchase()
            ->currency($billable->preferredCurrency());

        $productName = $options['product_name'] ?? 'One-time charge';
        $builder->addProductCents($productName, $amount);

        if ($billable->hasChipId()) {
            $builder->clientId($billable->chipId());
        } else {
            $builder->customer(
                email: $billable->chipEmail() ?? '',
                fullName: $billable->chipName()
            );
        }

        if (isset($options['success_url'])) {
            $builder->successUrl($options['success_url']);
        }

        if (isset($options['failure_url'])) {
            $builder->failureUrl($options['failure_url']);
        }

        if (isset($options['reference'])) {
            $builder->reference($options['reference']);
        }

        if (isset($options['idempotency_key']) && is_string($options['idempotency_key']) && $options['idempotency_key'] !== '') {
            $builder->idempotencyKey($options['idempotency_key']);
        }

        if ($metadata !== []) {
            $builder->metadata($metadata);
        }

        $purchase = $builder->create();

        if ($recurringToken) {
            $purchase = Cashier::chip()->chargePurchase($purchase->id, $recurringToken);
        }

        $payment = new Payment($purchase);

        if ($recurringToken) {
            $payment->validate();
        }

        return $payment;
    }

    /**
     * @param  Model&BillableContract  $billable
     * @return array<string, mixed>
     */
    private function billableMetadata(Model $billable, mixed $metadata): array
    {
        $resolved = is_array($metadata) ? $metadata : [];

        $resolved['billable_type'] = $billable->getMorphClass();
        $resolved['billable_id'] = (string) $billable->getKey();

        return $resolved;
    }
}
