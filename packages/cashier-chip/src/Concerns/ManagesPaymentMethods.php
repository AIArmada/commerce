<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Data\StoredPaymentMethodData;
use AIArmada\CashierChip\Features\PaymentMethods\Models\StoredPaymentMethod;
use AIArmada\CashierChip\Payment\PaymentMethod;
use AIArmada\CashierChip\Support\PaymentMethodMetadata;
use AIArmada\CashierChip\Support\RedirectUrlValidator;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\CommerceSupport\Data\RecordReference;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use SensitiveParameter;

trait ManagesPaymentMethods
{
    /**
     * @return MorphMany<StoredPaymentMethod, $this>
     */
    public function storedPaymentMethods(): MorphMany
    {
        /** @var class-string<StoredPaymentMethod> $model */
        $model = app('cashier-chip.models.stored-payment-method');

        return $this->morphMany($model, 'billable')
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
        if (! $this->exists || $this->getKey() === null) {
            return collect();
        }

        $paymentMethods = Cashier::paymentMethodStore()->allForBillable($this->paymentMethodSubject());

        if ($paymentMethods === [] && $this->hasChipId()) {
            $this->syncPaymentMethodsFromChip();
            $paymentMethods = Cashier::paymentMethodStore()->allForBillable($this->paymentMethodSubject());
        }

        return collect($paymentMethods)->map(fn ($paymentMethod) => new PaymentMethod($this, $paymentMethod));
    }

    /**
     * Get a specific recurring token by ID.
     */
    public function findPaymentMethod(#[SensitiveParameter] string $paymentMethodId): ?PaymentMethod
    {
        if (! $this->exists || $this->getKey() === null) {
            return null;
        }

        $paymentMethod = Cashier::paymentMethodStore()->findForBillable($this->paymentMethodSubject(), $paymentMethodId);

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
        if (! $this->exists || $this->getKey() === null) {
            return false;
        }

        if (Cashier::paymentMethodStore()->hasAnyForBillable($this->paymentMethodSubject())) {
            return true;
        }

        if (! $this->hasChipId()) {
            return false;
        }

        $this->syncPaymentMethodsFromChip();

        return Cashier::paymentMethodStore()->hasAnyForBillable($this->paymentMethodSubject());
    }

    /**
     * Get the default payment method for the customer.
     */
    public function defaultPaymentMethod(): ?PaymentMethod
    {
        if (! $this->exists || $this->getKey() === null) {
            return null;
        }

        $paymentMethod = Cashier::paymentMethodStore()->defaultForBillable($this->paymentMethodSubject());

        if ($paymentMethod === null && $this->hasChipId()) {
            $this->syncPaymentMethodsFromChip();
            $paymentMethod = Cashier::paymentMethodStore()->defaultForBillable($this->paymentMethodSubject());
        }

        return $paymentMethod === null ? null : new PaymentMethod($this, $paymentMethod);
    }

    public function getDefaultPmIdAttribute(): ?string
    {
        return $this->defaultPaymentMethod()?->id();
    }

    public function getPmTypeAttribute(): ?string
    {
        $method = $this->defaultPaymentMethod();

        return $method?->brand() ?? $method?->type();
    }

    public function getPmLastFourAttribute(): ?string
    {
        return $this->defaultPaymentMethod()?->lastFour();
    }

    /**
     * Update the default payment method for the customer.
     */
    public function updateDefaultPaymentMethod(#[SensitiveParameter] string $paymentMethodId): static
    {
        $paymentMethod = Cashier::paymentMethodStore()->setDefaultForBillable($this->paymentMethodSubject(), $paymentMethodId);

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

        $defaultMethod = Cashier::paymentMethodStore()->defaultForBillable($this->paymentMethodSubject());

        if ($defaultMethod !== null) {
            Cashier::paymentMethodStore()->setDefaultForBillable($this->paymentMethodSubject(), $defaultMethod->recurringToken());
        }

        return $this;
    }

    /**
     * Delete a payment method from the customer.
     */
    public function deletePaymentMethod(#[SensitiveParameter] string $paymentMethodId): void
    {
        Cashier::paymentMethodStore()->assertBillableAccess($this->paymentMethodSubject());

        if ($this->hasChipId()) {
            Cashier::chip()->deleteClientRecurringToken($this->chipId(), $paymentMethodId);
        }

        Cashier::paymentMethodStore()->deleteForBillable($this->paymentMethodSubject(), $paymentMethodId);
    }

    /**
     * Delete all payment methods from the customer.
     */
    public function deletePaymentMethods(): void
    {
        $tokens = Cashier::paymentMethodStore()->allForBillable($this->paymentMethodSubject());

        if ($this->hasChipId()) {
            foreach ($tokens as $token) {
                Cashier::chip()->deleteClientRecurringToken($this->chipId(), $token->recurringToken());
            }
        }

        Cashier::paymentMethodStore()->deleteAllForBillable($this->paymentMethodSubject());
    }

    /**
     * Create a setup purchase for adding payment methods.
     *
     * This creates a zero-amount preauthorization purchase that:
     * - Uses skip_capture=true to preauthorize without capturing
     * - Uses purchase.total_override=0 for zero-amount authorization
     * - Uses force_recurring=true to ensure a recurring token is saved
     *
     * On successful preauthorization, the webhook will receive purchase.preauthorized
     * event with the recurring_token that can be used for future charges.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSetupPurchase(array $options = []): PurchaseData
    {
        $idempotencyKey = $options['idempotency_key'] ?? null;

        if ($idempotencyKey === null && is_array($options['chip'] ?? null)) {
            $idempotencyKey = $options['chip']['idempotency_key'] ?? null;
        }

        if (! is_string($idempotencyKey) || mb_trim($idempotencyKey) === '') {
            throw new InvalidArgumentException(
                'An idempotency_key option is required when creating a setup purchase.',
            );
        }

        $idempotencyKey = mb_trim($idempotencyKey);

        // Ensure customer exists on CHIP - create if not already exists
        if (! $this->hasChipId()) {
            $this->createAsChipCustomer();
        }

        RedirectUrlValidator::assertValid($options['success_url'] ?? null, 'success_url');
        RedirectUrlValidator::assertValid($options['failure_url'] ?? null, 'failure_url');
        RedirectUrlValidator::assertValid($options['cancel_url'] ?? null, 'cancel_url');
        RedirectUrlValidator::assertValid($options['success_callback'] ?? null, 'success_callback');

        $chipOverrides = is_array($options['chip'] ?? null) ? $options['chip'] : [];
        $chipOverrides = array_intersect_key($chipOverrides, array_flip([
            'success_redirect',
            'failure_redirect',
            'cancel_redirect',
            'success_callback',
            'send_receipt',
        ]));

        foreach (['success_redirect', 'failure_redirect', 'cancel_redirect', 'success_callback'] as $key) {
            if (is_string($chipOverrides[$key] ?? null)) {
                RedirectUrlValidator::assertValid($chipOverrides[$key], 'chip.' . $key);
            }
        }

        $purchaseData = array_merge([
            'client_id' => $this->chipId(),
            'send_receipt' => false,
            'skip_capture' => true,
            'force_recurring' => true,
            'purchase' => [
                'currency' => config('cashier-chip.currency', 'MYR'),
                'subtotal_override' => 0,
                'total_discount_override' => 0,
                'total_tax_override' => 0,
                'total_override' => 0,
                'products' => [
                    [
                        'name' => $options['product_name'] ?? 'Payment Method Setup',
                        'price' => 0,
                        'quantity' => 1,
                    ],
                ],
            ],
            'brand_id' => config('chip.collect.brand_id'),
            'success_redirect' => $options['success_url'] ?? null,
            'failure_redirect' => $options['failure_url'] ?? null,
            'cancel_redirect' => $options['cancel_url'] ?? null,
            'success_callback' => $options['success_callback'] ?? null,
        ], $chipOverrides);

        foreach (['success_redirect', 'failure_redirect', 'cancel_redirect', 'success_callback'] as $key) {
            if ($purchaseData[$key] === null) {
                unset($purchaseData[$key]);
            }
        }

        $purchaseData['idempotency_key'] = $idempotencyKey;

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
        Cashier::paymentMethodStore()->assertBillableAccess($this->paymentMethodSubject());

        foreach ($this->chipRecurringTokens() as $token) {
            $tokenId = $token['id'] ?? null;

            if (! is_string($tokenId) || $tokenId === '') {
                continue;
            }

            Cashier::paymentMethodStore()->saveForBillable(
                $this->paymentMethodSubject(),
                $tokenId,
                $this->paymentMethodAttributesFromToken($token),
            );
        }
    }

    protected function syncPaymentMethodFromChip(string $paymentMethodId, bool $makeDefault = false): ?StoredPaymentMethodData
    {
        Cashier::paymentMethodStore()->assertBillableAccess($this->paymentMethodSubject());

        if (! $this->hasChipId()) {
            return null;
        }

        $token = Cashier::chip()->getClientRecurringToken($this->chipId(), $paymentMethodId);

        if (! is_array($token) || $token === []) {
            return null;
        }

        return Cashier::paymentMethodStore()->saveForBillable(
            $this->paymentMethodSubject(),
            $paymentMethodId,
            $this->paymentMethodAttributesFromToken($token),
            $makeDefault,
        );
    }

    private function paymentMethodSubject(): RecordReference
    {
        return new RecordReference($this->getMorphClass(), (string) $this->getKey());
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    protected function paymentMethodAttributesFromToken(array $token): array
    {
        return [
            'type' => $token['payment_method'] ?? null,
            'brand' => null,
            'last_four' => null,
            'metadata' => PaymentMethodMetadata::fromToken($token),
        ];
    }
}
