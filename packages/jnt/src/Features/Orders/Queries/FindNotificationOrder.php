<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Features\Orders\Api\NotificationOrder;
use AIArmada\Jnt\Features\Orders\Api\ReadNotificationOrder;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;

final readonly class FindNotificationOrder implements ReadNotificationOrder
{
    public function find(string $id, ?string $ownerType, string | int | null $ownerId): ?NotificationOrder
    {
        if ($id === '' || ($ownerType === null) !== ($ownerId === null)) {
            return null;
        }

        $owner = OwnerContext::fromTypeAndId($ownerType, $ownerId);

        if ($ownerType !== null && $owner === null) {
            return null;
        }

        $order = JntOrder::query()->forOwner($owner, false)->whereKey($id)->first();

        if ($order === null || $order->owner_type !== $ownerType || (string) $order->owner_id !== (string) $ownerId) {
            return null;
        }

        $email = $order->metadata['notification_email'] ?? null;

        return new NotificationOrder(
            $order->order_id,
            $order->tracking_number,
            $order->owner_type,
            is_string($email) ? $email : null,
        );
    }
}
