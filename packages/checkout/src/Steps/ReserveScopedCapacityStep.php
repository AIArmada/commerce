<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Contracts\ScopedCapacityCheckoutProviderInterface;
use AIArmada\Checkout\Data\StepResult;
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

    public function handle(CheckoutSession $session): StepResult
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

        try {
            $pricingData = $session->pricing_data ?? [];
            $pricingData['scoped_capacity_reservation'] = [
                'reference' => $reference,
                'expires_at' => $outcome->expiresAt,
            ];

            if (! $session->persistState(['pricing_data' => $pricingData])) {
                throw new \RuntimeException('Checkout session could not persist the capacity hold reference.');
            }
        } catch (Throwable $exception) {
            // The hold is not recoverable from the session if persistence
            // failed. Release it immediately rather than leaking capacity.
            try {
                app(ScopedCapacityReservationServiceInterface::class)->release($reference);
            } catch (Throwable $releaseException) {
                return $this->failed('Failed to persist or release scoped capacity hold', [
                    'capacity' => $exception->getMessage(),
                    'release' => $releaseException->getMessage(),
                ]);
            }

            return $this->failed('Failed to persist scoped capacity hold', [
                'capacity' => $exception->getMessage(),
            ]);
        }

        return $this->success('Scoped resource capacity held', [
            'reference' => $reference,
            'expires_at' => $outcome->expiresAt,
        ]);
    }

    public function compensate(CheckoutSession $session): StepResult
    {
        $reservation = data_get($session->pricing_data ?? [], 'scoped_capacity_reservation');

        if (! is_array($reservation) || ! is_string($reservation['reference'] ?? null)) {
            return $this->compensated('No scoped capacity reservation');
        }

        try {
            $capacity = app(ScopedCapacityReservationServiceInterface::class);
            $current = $capacity->find($reservation['reference']);

            // Confirmed capacity belongs to the created order, not to a
            // temporary checkout hold. A failed subsequent optional step
            // must not silently cancel a committed allocation.
            if ($current->state === 'committed') {
                return $this->compensated('Scoped capacity already committed to an order; not released');
            }

            $capacity->release($reservation['reference']);
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
