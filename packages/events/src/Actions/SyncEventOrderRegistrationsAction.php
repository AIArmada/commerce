<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\ModelResolver;
use InvalidArgumentException;

final class SyncEventOrderRegistrationsAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
    ) {}

    /**
     * @param  list<string>|null  $registrationIds
     */
    public function handle(string $orderId, string $orderType, string $eventType, ?array $registrationIds = null): int
    {
        if (! in_array($eventType, ['paid', 'cancelled', 'refunded', 'refund_failed'], true)) {
            throw new InvalidArgumentException("Unsupported event order lifecycle type: {$eventType}.");
        }

        $registrationClass = ModelResolver::registrationClass();

        if ($registrationIds !== null && array_filter($registrationIds, static fn (mixed $id): bool => is_string($id) && $id !== '') !== $registrationIds) {
            throw new InvalidArgumentException('Registration IDs must be a list of non-empty strings.');
        }

        $registrations = $registrationClass::query()
            ->where('external_order_id', $orderId)
            ->where('external_order_type', $orderType)
            ->when($registrationIds !== null, fn ($query) => $query->whereIn('id', $registrationIds))
            ->get();

        $count = 0;
        foreach ($registrations as $registration) {
            match ($eventType) {
                'paid' => $this->syncPaid($registration),
                'cancelled' => $this->syncCancelled($registration),
                'refunded' => $this->syncRefunded($registration),
                'refund_failed' => $this->syncRefundFailed($registration),
            };
            $count++;
        }

        return $count;
    }

    private function syncPaid(EventRegistration $registration): void
    {
        $this->registrationService->approve($registration);
        $this->setPaymentStatus($registration, 'paid');
    }

    private function syncCancelled(EventRegistration $registration): void
    {
        $this->registrationService->cancel($registration, 'Order cancelled');
        $this->setPaymentStatus($registration, 'cancelled');
    }

    private function syncRefunded(EventRegistration $registration): void
    {
        $this->registrationService->refund($registration, 'Order refunded');
        $this->setPaymentStatus($registration, 'refunded');
    }

    private function syncRefundFailed(EventRegistration $registration): void
    {
        $this->registrationService->restoreFromRefundPending($registration, 'Refund failed');
        $this->setPaymentStatus($registration, 'paid');
    }

    private function setPaymentStatus(EventRegistration $registration, string $paymentStatus): void
    {
        if ($registration->payment_status === $paymentStatus) {
            return;
        }

        $registration->forceFill(['payment_status' => $paymentStatus])->save();
    }
}
