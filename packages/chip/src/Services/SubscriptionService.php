<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use InvalidArgumentException;

class SubscriptionService
{
    public function __construct(
        private ChipCollectService $chipService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createWithFreeTrial(array $data, ?string $idempotencyKey = null): PurchaseData
    {
        $trialData = [
            'client' => $data['client'],
            'purchase' => [
                'products' => [
                    [
                        'name' => $data['trial_product_name'] ?? 'Free Trial',
                        'price' => 0,
                    ],
                ],
            ],
            'skip_capture' => true,
            'brand_id' => $this->resolveBrandId($data),
            'payment_method_whitelist' => $data['payment_method_whitelist'] ?? ['visa', 'mastercard', 'maestro'],
        ];

        return $this->chipService->createPurchase($this->withCurrency($trialData, $data), $this->resolveLeafKey($data, $idempotencyKey));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createWithRegistrationFee(array $data, ?string $idempotencyKey = null): PurchaseData
    {
        $registrationData = [
            'client' => $data['client'],
            'purchase' => [
                'products' => [
                    [
                        'name' => $data['registration_product_name'] ?? 'Registration Fee',
                        'price' => $data['registration_fee'],
                    ],
                ],
            ],
            'payment_method_whitelist' => $data['payment_method_whitelist'] ?? ['visa', 'mastercard', 'maestro'],
            'force_recurring' => true,
            'brand_id' => $this->resolveBrandId($data),
        ];

        return $this->chipService->createPurchase($this->withCurrency($registrationData, $data), $this->resolveLeafKey($data, $idempotencyKey));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSubscriptionPayment(array $data, ?string $idempotencyKey = null): PurchaseData
    {
        $subscriptionData = [
            'client' => $data['client'],
            'purchase' => [
                'products' => [
                    [
                        'name' => $data['product_name'] ?? 'Subscription Fee',
                        'price' => $data['amount'],
                    ],
                ],
            ],
            'brand_id' => $this->resolveBrandId($data),
        ];

        return $this->chipService->createPurchase($this->withCurrency($subscriptionData, $data), $this->resolveLeafKey($data, $idempotencyKey));
    }

    /**
     * Forward the caller currency into the built payload when present.
     * Validation stays in PurchasesApi; a missing currency throws there.
     *
     * @param  array<string, mixed>  $built
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withCurrency(array $built, array $data): array
    {
        if (isset($data['currency'])) {
            $built['purchase']['currency'] = $data['currency'];
        }

        return $built;
    }

    public function chargeSubscription(string $subscriptionPurchaseId, string $recurringToken): PurchaseData
    {
        return $this->chipService->chargePurchase($subscriptionPurchaseId, $recurringToken);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createMonthlySubscription(array $data): array
    {
        $hasRegistrationFee = isset($data['registration_fee']) && $data['registration_fee'] > 0;
        $hasFreeTrial = isset($data['trial_days']) && $data['trial_days'] > 0;

        // Child keys ride the 2nd idempotency argument, never $data['reference'].
        $baseKey = $this->resolveLeafKey($data, null);
        $initialKey = $baseKey !== null ? $baseKey . '-initial' : null;
        $subscriptionKey = $baseKey !== null ? $baseKey . '-subscription' : null;

        // Step 1: Create initial purchase (trial or registration)
        if ($hasFreeTrial) {
            $initialPurchase = $this->createWithFreeTrial($data, $initialKey);
        } elseif ($hasRegistrationFee) {
            $initialPurchase = $this->createWithRegistrationFee($data, $initialKey);
        } else {
            throw new InvalidArgumentException('Either registration_fee or trial_days must be provided');
        }

        // Step 2: Create the recurring subscription purchase
        $subscriptionInput = [
            'client' => $data['client'],
            'amount' => $data['amount'],
            'product_name' => $data['product_name'] ?? 'Monthly Subscription',
            'brand_id' => $this->resolveBrandId($data),
        ];

        if (isset($data['currency'])) {
            $subscriptionInput['currency'] = $data['currency'];
        }

        $subscriptionPurchase = $this->createSubscriptionPayment($subscriptionInput, $subscriptionKey);

        return [
            'initial_purchase' => $initialPurchase,
            'subscription_purchase' => $subscriptionPurchase,
        ];
    }

    /**
     * Forward the leaf-level key verbatim: no truncation, normalization,
     * or prefixing. Any length/charset enforcement happens at the gateway,
     * not silently here. An explicit argument wins; otherwise the durable
     * merchant-visible `$data['reference']` is used when present.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveLeafKey(array $data, ?string $idempotencyKey): ?string
    {
        if ($idempotencyKey !== null) {
            return $idempotencyKey;
        }

        $reference = $data['reference'] ?? null;

        if ($reference !== null && ! is_string($reference)) {
            throw new ChipValidationException('Subscription reference must be a string.');
        }

        return $reference;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveBrandId(array $data): string
    {
        $brandId = (string) ($data['brand_id'] ?? $this->chipService->getBrandId());

        if ($brandId === '') {
            throw new ChipValidationException('brand_id is required to create CHIP subscriptions');
        }

        return $brandId;
    }
}
