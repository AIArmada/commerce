<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\EventRegistration;

final class SyncEventOrderRegistrationsAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
    ) {}

    public function handle(string $orderId, string $orderType, string $eventType): int
    {
        $registrations = EventRegistration::query()
            ->where('external_order_id', $orderId)
            ->where('external_order_type', $orderType)
            ->get();

        $count = 0;
        foreach ($registrations as $registration) {
            match ($eventType) {
                'paid' => $this->registrationService->approve($registration),
                'cancelled' => $this->registrationService->cancel($registration, 'Order cancelled'),
                'refunded' => $this->registrationService->cancel($registration, 'Order refunded'),
                default => null,
            };
            $count++;
        }

        return $count;
    }
}
