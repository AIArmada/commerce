<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Data\ReservationOutcome;

final class InventoryAdapter
{
    /** @param list<ReservationLine> $lines */
    public function reserve(string $reference, array $lines, int $ttl = 900): ReservationOutcome
    {
        if (! $this->isInventoryPackageInstalled()) {
            return new ReservationOutcome(reference: $reference, state: 'not_managed');
        }

        return $this->getService()->reserve($reference, $lines, $ttl);
    }

    public function release(string $reference): ReservationOutcome
    {
        if (! $this->isInventoryPackageInstalled()) {
            return new ReservationOutcome(reference: $reference, state: 'not_managed');
        }

        return $this->getService()->release($reference);
    }

    public function commit(string $reference, string $orderId): ReservationOutcome
    {
        if (! $this->isInventoryPackageInstalled()) {
            return new ReservationOutcome(reference: $reference, state: 'not_managed');
        }

        return $this->getService()->commit($reference, $orderId);
    }

    public function extend(string $reference, int $ttl): ReservationOutcome
    {
        if (! $this->isInventoryPackageInstalled()) {
            return new ReservationOutcome(reference: $reference, state: 'not_managed');
        }

        return $this->getService()->extend($reference, $ttl);
    }

    public function find(string $reference): ReservationOutcome
    {
        if (! $this->isInventoryPackageInstalled()) {
            return new ReservationOutcome(reference: $reference, state: 'not_managed');
        }

        return $this->getService()->find($reference);
    }

    public function isInventoryPackageInstalled(): bool
    {
        return interface_exists(CheckoutReservationServiceInterface::class);
    }

    private function getService(): CheckoutReservationServiceInterface
    {
        return app(CheckoutReservationServiceInterface::class);
    }
}
