<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\Log;

final class SyncEventOrderRegistrationsOnOrderRefunded
{
    public function handle(object $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        OwnerContext::withOwner($event->order->owner ?? null, function () use ($event): void {
            $action = app(SyncEventOrderRegistrationsAction::class);
            $metadata = is_array($event->metadata ?? null) ? $event->metadata : [];
            $scope = $metadata['event_registration_scope'] ?? null;

            if ($scope === 'full_order') {
                $registrationIds = null;
            } elseif (array_key_exists('event_registration_ids', $metadata)) {
                $candidateIds = $metadata['event_registration_ids'];

                if (! is_array($candidateIds) || $candidateIds === [] || array_filter(
                    $candidateIds,
                    static fn (mixed $id): bool => is_string($id) && $id !== '',
                ) !== $candidateIds) {
                    Log::warning('Skipped event registration refund synchronization because its registration scope was malformed.', [
                        'order_id' => $event->order->getKey(),
                    ]);

                    return;
                }

                $registrationIds = array_values($candidateIds);
            } elseif ($scope === null) {
                // Preserve legacy full-order refunds that pre-date scoped refund metadata.
                $registrationIds = null;
            } else {
                Log::warning('Skipped event registration refund synchronization because its scope was unsupported.', [
                    'order_id' => $event->order->getKey(),
                    'scope' => $scope,
                ]);

                return;
            }

            $action->handle($event->order->id, Order::class, 'refunded', $registrationIds);
        });
    }
}
