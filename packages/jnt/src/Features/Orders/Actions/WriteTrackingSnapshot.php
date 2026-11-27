<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Jnt\Features\Orders\Api\UpdateTrackingSnapshot;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use Illuminate\Support\Facades\DB;

final readonly class WriteTrackingSnapshot implements UpdateTrackingSnapshot
{
    public function handle(string $orderId, array $attributes): void
    {
        $attributes = array_intersect_key($attributes, array_flip([
            'status', 'last_status_code', 'last_status', 'last_tracked_at',
            'problem_at', 'exception_at', 'returned_at', 'resolved_at', 'delivered_at',
        ]));

        DB::transaction(function () use ($orderId, $attributes): void {
            if ((bool) config('jnt.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(JntOrder::class, $orderId);
            }

            $order = JntOrder::query()->lockForUpdate()->findOrFail($orderId);
            $order->fill($attributes);
            $order->save();
        });
    }
}
