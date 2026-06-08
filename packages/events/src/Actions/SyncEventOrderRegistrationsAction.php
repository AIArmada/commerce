<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Collection;

final class SyncEventOrderRegistrationsAction
{
    public function __construct(
        private readonly RegistrationService $registrations,
    ) {}

    public function cancel(Order $order, string $reason): int
    {
        return $this->withOrderOwnerContext($order, function () use ($order, $reason): int {
            $registrations = $this->registrationsForOrder($order)
                ->filter(static function (Registration $registration): bool {
                    return in_array($registration->status->value, [
                        RegistrationStatus::Pending->value,
                        RegistrationStatus::Confirmed->value,
                        RegistrationStatus::Waitlisted->value,
                    ], true);
                });

            foreach ($registrations as $registration) {
                $this->registrations->cancel($registration, $reason);
            }

            return $registrations->count();
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function refund(Order $order, int $amount, string $reason, array $metadata = []): int
    {
        if ($amount < (int) $order->grand_total) {
            return 0;
        }

        return $this->withOrderOwnerContext($order, function () use ($order, $reason, $metadata): int {
            $registrations = $this->registrationsForOrder($order)
                ->filter(static function (Registration $registration): bool {
                    return $registration->status->value !== RegistrationStatus::Refunded->value;
                });

            foreach ($registrations as $registration) {
                $this->registrations->refund($registration, $reason, $metadata);
            }

            return $registrations->count();
        });
    }

    /**
     * @return Collection<int, Registration>
     */
    private function registrationsForOrder(Order $order): Collection
    {
        return Registration::query()
            ->where('order_id', $order->getKey())
            ->get();
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withOrderOwnerContext(Order $order, callable $callback): mixed
    {
        $owner = OwnerContext::fromTypeAndId($order->owner_type, $order->owner_id);

        return OwnerContext::withOwner($owner, $callback);
    }
}
