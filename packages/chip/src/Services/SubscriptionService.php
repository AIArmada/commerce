<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\DataObjects\Purchase;
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
    public function createWithFreeTrial(array $data): Purchase
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

        return $this->chipService->createPurchase($trialData);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createWithRegistrationFee(array $data): Purchase
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

        return $this->chipService->createPurchase($registrationData);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSubscriptionPayment(array $data): Purchase
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

        return $this->chipService->createPurchase($subscriptionData);
    }

    public function chargeSubscription(string $subscriptionPurchaseId, string $recurringToken): Purchase
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

        // Step 1: Create initial purchase (trial or registration)
        if ($hasFreeTrial) {
            $initialPurchase = $this->createWithFreeTrial($data);
        } elseif ($hasRegistrationFee) {
            $initialPurchase = $this->createWithRegistrationFee($data);
        } else {
            throw new InvalidArgumentException('Either registration_fee or trial_days must be provided');
        }

        // Step 2: Create the recurring subscription purchase
        $subscriptionPurchase = $this->createSubscriptionPayment([
            'client' => $data['client'],
            'amount' => $data['amount'],
            'product_name' => $data['product_name'] ?? 'Monthly Subscription',
            'brand_id' => $this->resolveBrandId($data),
        ]);

        return [
            'initial_purchase' => $initialPurchase,
            'subscription_purchase' => $subscriptionPurchase,
        ];
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
