<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Data\TrackingStatus;
use AIArmada\Jnt\Features\Orders\Api\NotificationOrder;
use AIArmada\Jnt\Features\Orders\Api\ReadNotificationOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Events\Dispatchable;
use InvalidArgumentException;

final class JntOrderStatusChanged
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly string $orderKey,
        public readonly ?string $orderReference,
        public readonly ?string $trackingNumber,
        public readonly ?string $ownerType,
        public readonly string | int | null $ownerId,
        public readonly TrackingStatus $currentStatus,
        public readonly ?string $previousStatusCode = null,
    ) {}

    public function owner(): ?Model
    {
        try {
            return OwnerContext::fromTypeAndIdOrFail($this->ownerType, $this->ownerId);
        } catch (InvalidArgumentException | ModelNotFoundException) {
            return null;
        }
    }

    public function resolveOrder(): ?NotificationOrder
    {
        return app(ReadNotificationOrder::class)->find($this->orderKey, $this->ownerType, $this->ownerId);
    }

    public function getOrderId(): string
    {
        return $this->orderReference ?? '';
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function isDelivered(): bool
    {
        return $this->currentStatus === TrackingStatus::Delivered;
    }

    public function hasException(): bool
    {
        return $this->currentStatus === TrackingStatus::Exception;
    }

    public function isReturning(): bool
    {
        return $this->currentStatus->isReturn();
    }

    public function requiresAttention(): bool
    {
        return $this->currentStatus->requiresAttention();
    }

    public function isTerminal(): bool
    {
        return $this->currentStatus->isTerminal();
    }
}
