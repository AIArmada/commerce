<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Jnt\Features\Orders\Api\CancelStoredOrder;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Services\JntExpressService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CancelStoredJntOrder implements CancelStoredOrder
{
    public function execute(string $orderId, string $reason): void
    {
        $reason = mb_trim($reason);

        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new DomainException('A valid cancellation reason is required.');
        }

        DB::transaction(function () use ($orderId, $reason): void {
            if ((bool) config('jnt.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(JntOrder::class, $orderId);
            }

            $order = JntOrder::query()->lockForUpdate()->findOrFail($orderId);

            if (in_array($order->status, ['delivered', 'cancelled', 'returned'], true)) {
                throw new DomainException('This shipping order cannot be cancelled.');
            }

            app(JntExpressService::class)->cancelOrder(
                orderId: $order->order_id,
                reason: $reason,
                trackingNumber: $order->tracking_number,
            );

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => CarbonImmutable::now(),
                'cancellation_reason' => $reason,
            ]);
        });
    }
}
