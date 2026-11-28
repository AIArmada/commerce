<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Exceptions\JntConfigurationException;
use AIArmada\Jnt\Features\Orders\Api\CancelStoredOrder;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Services\JntExpressService;
use Carbon\CarbonImmutable;
use DomainException;
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

        $order = DB::transaction(function () use ($orderId): JntOrder {
            $order = $this->lock($orderId);

            if ($order->cancellation_started_at !== null) {
                throw new DomainException('A cancellation has already started. Verify its outcome with the carrier.');
            }

            if (! $order->isCancellable()) {
                throw new DomainException('This shipping order cannot be cancelled.');
            }

            $order->update(['cancellation_started_at' => CarbonImmutable::now()]);

            return $order;
        });

        try {
            $response = app(JntExpressService::class)->cancelOrder(
                orderId: $order->order_id,
                reason: $reason,
                trackingNumber: $order->tracking_number,
            );
        } catch (Throwable $exception) {
            if ($this->wasRejected($exception)) {
                $this->release($orderId);
            } else {
                Log::warning('J&T cancellation outcome is unknown', [
                    'order_id' => $order->order_id,
                    'exception_type' => $exception::class,
                ]);
            }

            throw $exception;
        }

        if (($response['success'] ?? null) === false) {
            $this->release($orderId);

            throw new DomainException('The carrier rejected the cancellation.');
        }

        DB::transaction(function () use ($orderId, $reason): void {
            $order = $this->lock($orderId);

            if ($order->hasTerminalStatus()) {
                throw new DomainException('The order changed during cancellation. Verify its outcome with the carrier.');
            }

            $order->update([
                'status' => 'cancelled',
                'cancellation_started_at' => null,
                'cancelled_at' => CarbonImmutable::now(),
                'cancellation_reason' => $reason,
            ]);
        });
    }

    private function wasRejected(Throwable $exception): bool
    {
        return $exception instanceof JntConfigurationException
            || ($exception instanceof JntApiException
                && is_array($exception->apiResponse)
                && (is_int($exception->apiResponse['code'] ?? null) || is_string($exception->apiResponse['code'] ?? null))
                && (string) $exception->apiResponse['code'] === $exception->errorCode
                && $exception->errorCode !== '1');
    }

    private function release(string $orderId): void
    {
        DB::transaction(fn () => $this->lock($orderId)->update(['cancellation_started_at' => null]));
    }

    private function lock(string $orderId): JntOrder
    {
        if ((bool) config('jnt.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(JntOrder::class, $orderId);
        }

        return JntOrder::query()->lockForUpdate()->findOrFail($orderId);
    }
}
