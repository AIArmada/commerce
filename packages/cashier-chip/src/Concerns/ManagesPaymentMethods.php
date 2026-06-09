<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Payment\PaymentMethod;
use AIArmada\CashierChip\Payment\StoredPaymentMethod;
use AIArmada\Chip\Data\PurchaseData;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use SensitiveParameter;

trait ManagesPaymentMethods // @phpstan-ignore trait.unused
{
    /**
     * @return MorphMany<StoredPaymentMethod, $this>
     */
    public function storedPaymentMethods(): MorphMany
    {
        return $this->morphMany(StoredPaymentMethod::class, 'billable')
            ->orderByDesc('is_default')
            ->orderByDesc('created_at');
    }

    /**
     * Get the customer's recurring tokens (payment methods).
     *
     * @return Collection<int, PaymentMethod>
     */
    public function paymentMethods(): Collection
    {
        $paymentMethods = Cashier::paymentMethodStore()->allForBillable($this);

        if ($paymentMethods->isEmpty() && $this->hasChipId()) {
            $this->syncPaymentMethodsFromChip();
            $paymentMethods = Cashier::paymentMethodStore()->allForBillable($this);
        }

        return $paymentMethods->map(fn ($paymentMethod) => new PaymentMethod($this, $paymentMethod));
    }

    /**
     * Get a specific recurring token by ID.
     */
    public function findPaymentMethod(#[SensitiveParameter] string $paymentMethodId): ?PaymentMethod
    {
        $paymentMethod = Cashier::paymentMethodStore()->findForBillable($this, $paymentMethodId);

        if ($paymentMethod === null && $this->hasChipId()) {
            $paymentMethod = $this->syncPaymentMethodFromChip($paymentMethodId);
        }

        return $paymentMethod === null ? null : new PaymentMethod($this, $paymentMethod);
    }

    /**
     * Determine if the customer has a default payment method.
     */
    public function hasDefaultPaymentMethod(): bool
    {
        return $this->defaultPaymentMethod() !== null;
    }

    /**
     * Determine if the customer has any payment method.
     */
    public function hasPaymentMethod(): bool
    {
        if (! Cashier::paymentMethodStore()->hasAnyForBillable($this) && $this->hasChipId()) {
            $this->syncPaymentMethodsFromChip();
        }

        return Cashier::paymentMethodStore()->hasAnyForBillable($this);
    }

    /**
     * Get the default payment method for the customer.
     */
    public function defaultPaymentMethod(): ?PaymentMethod
    {
        $paymentMethod = Cashier::paymentMethodStore()->defaultForBillable($this);

        if ($paymentMethod === null && $this->hasChipId()) {
            $this->syncPaymentMethodsFromChip();
            $paymentMethod = Cashier::paymentMethodStore()->defaultForBillable($this);
        }

        return $paymentMethod === null ? null : new PaymentMethod($this, $paymentMethod);
    }

    public function getDefaultPmIdAttribute(): ?string
    {
        return $this->defaultPaymentMethod()?->id();
    }

    public function getPmTypeAttribute(): ?string
    {
        return $this->defaultPaymentMethod()?->brand() ?? $this->defaultPaymentMethod()?->type();
    }

    public function getPmLastFourAttribute(): ?string
    {
        return $this->defaultPaymentMethod()?->lastFour();
    }

    /**
     * Update the default payment method for the customer.
     */
    public function updateDefaultPaymentMethod(#[SensitiveParameter] string $paymentMethodId): self
    {
        $paymentMethod = Cashier::paymentMethodStore()->setDefaultForBillable($this, $paymentMethodId);

        if ($paymentMethod === null && $this->hasChipId()) {
            $syncedPaymentMethod = $this->syncPaymentMethodFromChip($paymentMethodId, makeDefault: true);

            if ($syncedPaymentMethod === null) {
                return $this;
            }
        }

        return $this;
    }

    /**
     * Update default payment method from CHIP.
     */
    public function updateDefaultPaymentMethodFromChip(): self
    {
        $this->syncPaymentMethodsFromChip();

        $defaultMethod = Cashier::paymentMethodStore()->defaultForBillable($this);

        if ($defaultMethod !== null) {
            Cashier::paymentMethodStore()->setDefaultForBillable($this, $defaultMethod->recurring_token);
        }

        return $this;
    }

    /**
     * Delete a payment method from the customer.
     */
    public function deletePaymentMethod(#[SensitiveParameter] string $paymentMethodId): void
    {
        if ($this->hasChipId()) {
            Cashier::chip()->deleteClientRecurringToken($this->chipId(), $paymentMethodId);
        }

        Cashier::paymentMethodStore()->deleteForBillable($this, $paymentMethodId);
    }

    /**
     * Delete all payment methods from the customer.
     */
    public function deletePaymentMethods(): void
    {
        foreach ($this->paymentMethods() as $paymentMethod) {
            $paymentMethod->delete();
        }

        Cashier::paymentMethodStore()->deleteAllForBillable($this);
    }

    /**
     * Create a setup purchase for adding payment methods.
     *
     * This creates a zero-amount preauthorization purchase that:
     * - Uses skip_capture=true to preauthorize without capturing
     * - Uses total_override=0 for zero-amount authorization
     * - Uses force_recurring=true to ensure a recurring token is saved
     *
     * On successful preauthorization, the webhook will receive purchase.preauthorized
     * event with the recurring_token that can be used for future charges.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSetupPurchase(array $options = []): PurchaseData
    {
        // Ensure customer exists on CHIP - create if not already exists
        if (! $this->hasChipId()) {
            $this->createAsChipCustomer();
        }

        $purchaseData = array_merge([
            'client_id' => $this->chipId(),
            'send_receipt' => false,
            'skip_capture' => true,
            'total_override' => 0,
            'force_recurring' => true,
            'purchase' => [
                'currency' => config('cashier-chip.currency', 'MYR'),
                'products' => [
                    [
                        'name' => $options['product_name'] ?? 'Payment Method Setup',
                        'price' => 0,
                        'quantity' => 1,
                    ],
                ],
            ],
            'brand_id' => config('chip.collect.brand_id'),
            'success_callback' => $options['success_url'] ?? null,
            'failure_callback' => $options['cancel_url'] ?? null,
            'success_redirect' => $options['success_url'] ?? null,
            'failure_redirect' => $options['cancel_url'] ?? null,
        ], $options['chip'] ?? []);

        return Cashier::chip()->createPurchase($purchaseData);
    }

    /**
     * Get the checkout URL for setting up a payment method.
     *
     * @param  array<string, mixed>  $options
     */
    public function setupPaymentMethodUrl(array $options = []): string
    {
        $purchase = $this->createSetupPurchase($options);

        return $purchase->checkout_url ?? '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function chipRecurringTokens(): array
    {
        if (! $this->hasChipId()) {
            return [];
        }

        $response = Cashier::chip()->listClientRecurringTokens($this->chipId());
        $results = $response['results'] ?? [];

        return is_array($results) ? array_values(array_filter($results, 'is_array')) : [];
    }

    protected function syncPaymentMethodsFromChip(): void
    {
        foreach ($this->chipRecurringTokens() as $index => $token) {
            $tokenId = $token['id'] ?? $token['recurring_token'] ?? null;

            if (! is_string($tokenId) || $tokenId === '') {
                continue;
            }

            $makeDefault = ($token['is_default'] ?? false) === true || $index === 0;

            Cashier::paymentMethodStore()->saveForBillable(
                $this,
                $tokenId,
                $this->paymentMethodAttributesFromToken($token),
                $makeDefault,
            );
        }
    }

    protected function syncPaymentMethodFromChip(string $paymentMethodId, bool $makeDefault = false): ?StoredPaymentMethod
    {
        if (! $this->hasChipId()) {
            return null;
        }

        $token = Cashier::chip()->getClientRecurringToken($this->chipId(), $paymentMethodId);

        if (! is_array($token) || $token === []) {
            return null;
        }

        return Cashier::paymentMethodStore()->saveForBillable(
            $this,
            $paymentMethodId,
            $this->paymentMethodAttributesFromToken($token),
            $makeDefault,
        );
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    protected function paymentMethodAttributesFromToken(array $token): array
    {
        return [
            'type' => $token['type'] ?? $token['payment_method'] ?? null,
            'brand' => $token['card_brand'] ?? $token['brand'] ?? null,
            'last_four' => $token['last_4'] ?? $token['card_last_4'] ?? $token['last_four'] ?? null,
            'metadata' => $token,
        ];
    }
}
