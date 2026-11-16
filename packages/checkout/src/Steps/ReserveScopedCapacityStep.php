<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Contracts\ScopedCapacityCheckoutProviderInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Inventory\Contracts\ScopedCapacityReservationServiceInterface;
use Illuminate\Support\Str;
use RuntimeException;
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
        // Inventory registers its reservation contract in a deferred
        // provider. An app()->bound() check can be false until make() loads
        // that provider, so only the application-owned policy is optional.
        return ! app()->bound(ScopedCapacityCheckoutProviderInterface::class);
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

        $pricingData = $session->pricing_data ?? [];
        $reference = data_get($pricingData, 'scoped_capacity_reservation.reference');

        if (! is_string($reference) || mb_trim($reference) === '') {
            // Persist the intent BEFORE allocating capacity. If a worker
            // crashes after reserve(), the same reference can be recovered
            // and retried without acquiring a second hold.
            $reference = 'checkout:' . $session->getKey() . ':' . Str::uuid();
            $pricingData['scoped_capacity_reservation'] = [
                'reference' => $reference,
                'expires_at' => null,
            ];

            try {
                if (! $session->persistState(['pricing_data' => $pricingData])) {
                    throw new RuntimeException('Checkout session could not persist the capacity reservation intent.');
                }
            } catch (Throwable $exception) {
                return $this->failed('Failed to persist capacity reservation intent', [
                    'capacity' => $exception->getMessage(),
                ]);
            }
        }

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
                throw new RuntimeException('Checkout session could not persist the confirmed capacity hold.');
            }
        } catch (Throwable $exception) {
            // The durable reference is available for recovery, but release
            // immediately on a failed checkout step to avoid leaking capacity.
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

        try {
            if (! $session->persistState(['pricing_data' => $pricingData])) {
                throw new RuntimeException('Checkout session could not clear the released capacity reference.');
            }
        } catch (Throwable $exception) {
            return $this->failed('Scoped capacity released but reference could not be cleared', [
                'capacity' => $exception->getMessage(),
            ]);
        }

        return $this->compensated('Scoped capacity released');
    }
}
