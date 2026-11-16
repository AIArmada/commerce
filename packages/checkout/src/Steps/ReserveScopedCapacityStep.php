<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Contracts\ScopedCapacityCheckoutProviderInterface;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Inventory\Contracts\ScopedCapacityReservationServiceInterface;
use Throwable;

final class ReserveScopedCapacityStep extends AbstractCheckoutStep
{
    public function getIdentifier(): string
    {
        return 'reserve_scoped_capacity';
    }

    public function getName(): string
    {
        return 'Reserve Scoped Capacity';
    }

    public function getDependencies(): array
    {
        return ['validate_cart', 'calculate_pricing'];
    }

    public function canSkip(CheckoutSession $session): bool
    {
        return ! app()->bound(ScopedCapacityCheckoutProviderInterface::class)
            || ! app()->bound(ScopedCapacityReservationServiceInterface::class);
    }

    public function handle(CheckoutSession $session): \AIArmada\Checkout\Data\StepResult
    {
        if ($this->canSkip($session)) {
            return $this->skipped('No scoped capacity integration configured');
        }

        $provider = app(ScopedCapacityCheckoutProviderInterface::class);
        $lines = $provider->linesFor($session);

        if ($lines === []) {
            return $this->skipped('No scoped capacity demand');
        }

        $reference = 'checkout:' . $session->getKey();
        $ttl = (int) config('checkout.integrations.scoped_capacity.reservation_ttl', 900);

        try {
            $outcome = app(ScopedCapacityReservationServiceInterface::class)->reserve($reference, $lines, $ttl);
        } catch (Throwable $exception) {
            return $this->failed('Scoped capacity reservation failed', [
                'capacity' => $exception->getMessage(),
            ]);
        }

        $pricingData = $session->pricing_data ?? [];
        $pricingData['scoped_capacity_reservation'] = [
            'reference' => $reference,
            'expires_at' => $outcome->expiresAt,
        ];
        $session->persistState(['pricing_data' => $pricingData]);

        return $this->success('Scoped resource capacity held', [
            'reference' => $reference,
            'expires_at' => $outcome->expiresAt,
        ]);
    }

    public function compensate(CheckoutSession $session): \AIArmada\Checkout\Data\StepResult
    {
        $reservation = data_get($session->pricing_data ?? [], 'scoped_capacity_reservation');

        if (! is_array($reservation) || ! is_string($reservation['reference'] ?? null)) {
            return $this->compensated('No scoped capacity reservation');
        }

        try {
            app(ScopedCapacityReservationServiceInterface::class)->release($reservation['reference']);
        } catch (Throwable $exception) {
            return $this->failed('Failed to release scoped capacity', [
                'capacity' => $exception->getMessage(),
            ]);
        }

        $pricingData = $session->pricing_data ?? [];
        unset($pricingData['scoped_capacity_reservation']);
        $session->persistState(['pricing_data' => $pricingData]);

        return $this->compensated('Scoped capacity released');
    }
}
