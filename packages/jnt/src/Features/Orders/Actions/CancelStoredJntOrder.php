<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Jnt\Features\Orders\Api\CancelStoredOrder;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Services\JntExpressService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CancelStoredJntOrder implements CancelStoredOrder
{
    public function execute(string $orderId, string $reason): void
    {
        $reason = mb_trim($reason);

        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new DomainException('A valid cancellation reason is required.');
        }

        Cache::lock("jnt:order:{$orderId}:cancel", 30)->block(30, function () use ($orderId, $reason): void {
            $order = $this->find($orderId);

            if (! $order->isCancellable()) {
                throw new DomainException('This shipping order cannot be cancelled.');
            }

            try {
                $response = app(JntExpressService::class)->cancelOrder(
                    orderId: $order->order_id,
                    reason: $reason,
                    trackingNumber: $order->tracking_number,
                );
            } catch (Throwable $exception) {
                Log::warning('J&T cancellation outcome is unknown', [
                    'order_id' => $order->order_id,
                    'exception_type' => $exception::class,
                ]);

                throw $exception;
            }

            $success = array_key_exists('success', $response)
                ? (bool) $response['success']
                : (string) ($response['code'] ?? '1') === '1';

            if (! $success) {
                throw new DomainException('The carrier rejected the cancellation.');
            }

            DB::transaction(function () use ($orderId, $reason): void {
                $order = $this->lock($orderId);

                if (! $order->isCancellable()) {
                    throw new DomainException('The order changed during cancellation. Reconcile tracking before retrying.');
                }

                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => CarbonImmutable::now(),
                    'cancellation_reason' => $reason,
                ]);
            });
        });
    }

    private function find(string $orderId): JntOrder
    {
        if ((bool) config('jnt.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(JntOrder::class, $orderId);
        }

        return JntOrder::query()->findOrFail($orderId);
    }

    private function lock(string $orderId): JntOrder
    {
        if ((bool) config('jnt.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(JntOrder::class, $orderId);
        }

        return JntOrder::query()->lockForUpdate()->findOrFail($orderId);
    }
}
