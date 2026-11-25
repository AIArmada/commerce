<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\EntryPoints\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Commerce\Actions\SyncEventOrderRegistrationsAction;
use AIArmada\Events\Platform\CommerceIntegration;
use AIArmada\Orders\Events\OrderRefunded;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

final class SyncEventOrderRegistrationsOnOrderRefunded
{
    public function __construct(
        private readonly SyncEventOrderRegistrationsAction $syncRegistrations,
    ) {}

    public function handle(OrderRefunded $event): void
    {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            return;
        }

        $order = $event->order;
        $owner = $order->getRelationValue('owner');

        OwnerContext::withOwner($owner instanceof Model ? $owner : null, function () use ($event): void {
            $metadata = $event->metadata;
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
            } else {
                Log::warning('Skipped event registration refund synchronization because its scope was unsupported.', [
                    'order_id' => $event->order->getKey(),
                    'scope' => $scope,
                ]);

                return;
            }

            $this->syncRegistrations->handle($event->order->getKey(), Order::class, 'refunded', $registrationIds);
        });
    }
}
