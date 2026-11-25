<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Features\Registrations\Api\RegistrationSummary;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SyncEventOrderRegistrationsAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
        private readonly RegistrationsApi $registrations,
    ) {}

    /**
     * @param  list<string>|null  $registrationIds
     */
    public function handle(string $orderId, string $orderType, string $eventType, ?array $registrationIds = null): int
    {
        if (! in_array($eventType, ['paid', 'free', 'cancelled', 'refunded', 'refund_failed'], true)) {
            throw new InvalidArgumentException("Unsupported event order lifecycle type: {$eventType}.");
        }

        if ($registrationIds !== null && array_filter($registrationIds, static fn (mixed $id): bool => is_string($id) && $id !== '') !== $registrationIds) {
            throw new InvalidArgumentException('Registration IDs must be a list of non-empty strings.');
        }

        $count = 0;

        foreach ($this->registrations->forOrder($orderId, $orderType, $registrationIds) as $summary) {
            match ($eventType) {
                'paid' => $this->syncPaid($summary),
                'free' => $this->syncFree($summary),
                'cancelled' => $this->syncCancelled($summary),
                'refunded' => $this->syncRefunded($summary),
                'refund_failed' => $this->syncRefundFailed($summary),
            };
            $count++;
        }

        return $count;
    }

    private function syncPaid(RegistrationSummary $summary): void
    {
        DB::transaction(function () use ($summary): void {
            // Serialize overlapping fulfillment deliveries per
            // registration: approve on a locked fresh copy so only the
            // delivery that performs the transition emits the approval.
            $this->registrations->lock($summary->id);

            $this->registrationService->approve($summary->id);
            $this->registrations->recordPaymentStatus($summary->id, 'paid');
        });
    }

    private function syncFree(RegistrationSummary $summary): void
    {
        DB::transaction(function () use ($summary): void {
            // Same locked approve as the paid path, but the payment
            // status is already 'free' from creation: never write it.
            $this->registrations->lock($summary->id);

            $this->registrationService->approve($summary->id);
        });
    }

    private function syncCancelled(RegistrationSummary $summary): void
    {
        $this->registrationService->cancel($summary->id, 'Order cancelled');
        $this->registrations->recordPaymentStatus($summary->id, 'cancelled');
    }

    private function syncRefunded(RegistrationSummary $summary): void
    {
        $this->registrationService->refund($summary->id, 'Order refunded');
        $this->registrations->recordPaymentStatus($summary->id, 'refunded');
    }

    private function syncRefundFailed(RegistrationSummary $summary): void
    {
        $this->registrationService->restoreFromRefundPending($summary->id, 'Refund failed');
        $this->registrations->recordPaymentStatus($summary->id, 'paid');
    }
}
