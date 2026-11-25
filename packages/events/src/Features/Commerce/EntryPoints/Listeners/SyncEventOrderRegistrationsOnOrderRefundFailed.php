<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Commerce\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Events\Platform\CommerceIntegration;
use AIArmada\Orders\Events\OrderRefundFailed;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

final class SyncEventOrderRegistrationsOnOrderRefundFailed
{
    public function __construct(
        private readonly SyncEventOrderRegistrationsAction $syncRegistrations,
    ) {}

    public function handle(OrderRefundFailed $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        $order = $event->order;
        $owner = $order->getRelationValue('owner');

        OwnerContext::withOwner($owner instanceof Model ? $owner : null, function () use ($event): void {
            $metadata = $event->metadata;
            $scope = $metadata['event_registration_scope'] ?? null;

            if ($scope === 'full_order' || ($scope === null && ! array_key_exists('event_registration_ids', $metadata))) {
                $registrationIds = null;
            } else {
                $candidateIds = $metadata['event_registration_ids'] ?? null;

                if ($scope !== 'selected' || ! is_array($candidateIds) || $candidateIds === [] || array_filter(
                    $candidateIds,
                    static fn (mixed $id): bool => is_string($id) && $id !== '',
                ) !== $candidateIds) {
                    Log::warning('Skipped event registration restoration because refund failure scope was malformed.', [
                        'order_id' => $event->order->getKey(),
                    ]);

                    return;
                }

                $registrationIds = array_values($candidateIds);
            }

            $this->syncRegistrations->handle(
                $event->order->getKey(),
                Order::class,
                'refund_failed',
                $registrationIds,
            );
        });
    }
}
