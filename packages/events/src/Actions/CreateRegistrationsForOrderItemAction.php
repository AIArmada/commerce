<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

final class CreateRegistrationsForOrderItemAction
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @return Collection<int, Registration>
     */
    public function handle(Occurrence $occurrence, OrderItem $orderItem, array $participants, ?Customer $purchaser = null): Collection
    {
        $owner = OwnerContext::fromTypeAndId($occurrence->owner_type, $occurrence->owner_id);

        return OwnerContext::withOwner($owner, function () use ($occurrence, $orderItem, $participants, $purchaser): Collection {
            $expectedCount = max(1, (int) $orderItem->quantity);
            $existing = Registration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('order_item_id', $orderItem->id)
                ->get();

            if ($existing->isNotEmpty()) {
                if ($existing->count() !== $expectedCount) {
                    throw new InvalidArgumentException(sprintf(
                        'Expected %d existing registrations for order item %s, found %d.',
                        $expectedCount,
                        (string) $orderItem->id,
                        $existing->count(),
                    ));
                }

                return $existing;
            }

            return $this->registrationService->createBatchForOrderItem(
                $occurrence,
                $orderItem,
                $participants,
                $purchaser,
            );
        });
    }
}
