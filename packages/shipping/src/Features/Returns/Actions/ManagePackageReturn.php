<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Returns\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Shipping\Features\Returns\Api\PackageReturnWorkflow;
use AIArmada\Shipping\Features\Returns\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\Shipment;

final readonly class ManagePackageReturn implements PackageReturnWorkflow
{
    public function __construct(private PackageReturns $returns) {}

    public function shippedUnits(string $shipmentId): array
    {
        return $this->returns->shippedUnits($this->shipment($shipmentId));
    }

    public function request(string $shipmentId, string $orderItemId, int $ordinal, string $reason): string
    {
        return (string) $this->returns->request($this->shipment($shipmentId), $orderItemId, $ordinal, $reason)->getKey();
    }

    public function receive(string $authorizationId, array $inspection, ?string $locationId = null): string
    {
        return (string) $this->returns->receive($this->authorization($authorizationId), $inspection, $locationId)->getKey();
    }

    public function complete(string $authorizationId, ?string $transactionId = null): string
    {
        return (string) $this->returns->complete($this->authorization($authorizationId), $transactionId)->getKey();
    }

    private function shipment(string $id): Shipment
    {
        return (bool) config('shipping.features.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(Shipment::class, $id)
            : Shipment::query()->findOrFail($id);
    }

    private function authorization(string $id): ReturnAuthorization
    {
        return (bool) config('shipping.features.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(ReturnAuthorization::class, $id)
            : ReturnAuthorization::query()->findOrFail($id);
    }
}
