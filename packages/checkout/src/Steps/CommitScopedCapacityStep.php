<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Inventory\Contracts\ScopedCapacityReservationServiceInterface;
use Throwable;

final class CommitScopedCapacityStep extends AbstractCheckoutStep
{
    public function getIdentifier(): string
    {
        return 'commit_scoped_capacity';
    }

    public function getName(): string
    {
        return 'Confirm Scoped Capacity';
    }

    public function getDependencies(): array
    {
        return ['create_order'];
    }

    public function canSkip(CheckoutSession $session): bool
    {
        return ! is_array(data_get($session->pricing_data ?? [], 'scoped_capacity_reservation'));
    }

    public function handle(CheckoutSession $session): \AIArmada\Checkout\Data\StepResult
    {
        if ($this->canSkip($session)) {
            return $this->skipped('No scoped capacity hold');
        }

        $reference = data_get($session->pricing_data ?? [], 'scoped_capacity_reservation.reference');

        if (! is_string($reference) || $session->order_id === null) {
            return $this->failed('A scoped capacity hold requires a created order to confirm');
        }

        try {
            app(ScopedCapacityReservationServiceInterface::class)->commit($reference, (string) $session->order_id);
        } catch (Throwable $exception) {
            return $this->failed('Scoped capacity confirmation failed', [
                'capacity' => $exception->getMessage(),
            ]);
        }

        return $this->success('Scoped capacity confirmed for the order');
    }
}
