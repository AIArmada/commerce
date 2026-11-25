<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Contracts\EventRefundEligibility;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Data\EventRefundResultData;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Platform\CommerceIntegration;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

/**
 * Orchestrates paid event registration refunds via the Orders and Checkout abstractions.
 */
final readonly class ProcessEventRegistrationRefundAction
{
    public function __construct(
        private EventOrderDriver $orders,
        private EventRefundEligibility $eligibility,
        private RegistrationServiceInterface $registrations,
        private RegistrationsApi $registrationsApi,
        private CatalogApi $catalog,
    ) {}

    public function handle(
        string $eventId,
        string $registrationId,
        Model $actor,
        bool $organizerOverride = false,
        ?string $reason = null,
        bool $authorize = true,
    ): EventRefundResultData {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()) {
            throw new RuntimeException('Order and payment gateway integrations are required to refund paid registrations.');
        }

        $summary = $this->registrationsApi->summarize($registrationId);

        if ($summary === null || $summary->eventId !== $eventId) {
            throw new InvalidArgumentException('The selected registration does not belong to the selected event.');
        }

        if ($authorize) {
            if ($organizerOverride) {
                $this->catalog->authorizeManageAdmissions($actor, $eventId);
            } else {
                $this->registrationsApi->authorizeRefundRequest($actor, $registrationId);
            }
        }

        if ($organizerOverride) {
            if (! $this->eligibility->canOrganizerRefund($eventId, $registrationId)) {
                throw new InvalidArgumentException('This admission is not eligible for an organizer refund.');
            }
        } else {
            $eligibilityFailure = $this->eligibility->selfServiceRefundReason($actor, $eventId, $registrationId);

            if ($eligibilityFailure !== null) {
                throw new InvalidArgumentException(match ($eligibilityFailure) {
                    'refunds_disabled' => 'Refunds are not enabled for this event.',
                    'purchaser_only' => 'Only the purchaser can request a refund.',
                    'refund_window_closed' => 'The self-service refund window has closed.',
                    'admission_already_used' => 'A used admission cannot be refunded online.',
                    'free_admission' => 'Free admissions do not have a payment to refund.',
                    default => 'This admission is not eligible for a self-service refund.',
                });
            }
        }

        $orderId = $summary->externalOrderId;

        if ($orderId === null || ! $this->orders->isPaidOrder($orderId)) {
            throw new InvalidArgumentException('This order has no completed payment to refund.');
        }

        $mode = $organizerOverride ? 'organizer_approved' : 'self_service';
        $refundId = $this->orders->refundIdForRegistration($orderId, $registrationId);

        if ($refundId !== null) {
            $state = $this->orders->refundState($refundId);

            if ($state !== null) {
                if ($state->status === 'failed') {
                    throw new RuntimeException('The refund request has already failed and requires review.');
                }

                if ($state->status === 'completed') {
                    return new EventRefundResultData(status: 'completed', refundId: $refundId);
                }

                $this->markRegistrationRefundPending($registrationId);

                if ($state->providerSubmissionStarted) {
                    return new EventRefundResultData(status: 'pending', refundId: $refundId);
                }
            }
        }

        $amount = $refundId !== null
            ? (int) ($this->orders->refundState($refundId)?->amount ?? $summary->totalAmount)
            : $summary->totalAmount;
        $refundReason = $refundId !== null
            ? ($this->orders->refundState($refundId)?->reason ?? $this->normalizeReason($reason, $mode))
            : $this->normalizeReason($reason, $mode);

        if ($refundId === null) {
            $refundId = $this->orders->createRefund(
                orderId: $orderId,
                amount: $amount,
                reason: $refundReason,
                transactionId: 'event-refund-' . $registrationId,
                metadata: $this->refundMetadata($eventId, $registrationId, $actor, $mode, $amount),
            );
        }

        $this->markRegistrationRefundPending($registrationId);

        if (! $this->orders->claimRefund($refundId)) {
            return new EventRefundResultData(status: 'pending', refundId: $refundId);
        }

        $result = $this->orders->submitRefund($refundId);

        $this->orders->correlateRefundProviderReference($refundId, $result->transactionId, null);

        if (in_array($result->status, ['completed', 'refunded', 'partially_refunded'], true)) {
            $this->orders->completeRefund($refundId, $result->transactionId ?? $result->paymentId);

            return new EventRefundResultData(
                status: $result->status,
                transactionId: $result->transactionId,
                paymentId: $result->paymentId,
                message: $result->message,
                refundId: $refundId,
            );
        }

        if (in_array($result->status, ['failed', 'cancelled'], true)) {
            $this->orders->failRefund($refundId, $result->message ?? 'The payment provider rejected the refund.');

            throw new RuntimeException('The payment provider could not process the refund.');
        }

        return new EventRefundResultData(
            status: $result->status,
            transactionId: $result->transactionId,
            paymentId: $result->paymentId,
            message: $result->message,
            refundId: $refundId,
        );
    }

    private function markRegistrationRefundPending(string $registrationId): void
    {
        OwnerContext::withOwner(null, function () use ($registrationId): void {
            $this->registrations->markRefundPending(
                $registrationId,
                'Refund is awaiting payment provider confirmation.',
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function refundMetadata(
        string $eventId,
        string $registrationId,
        Model $actor,
        string $mode,
        int $amount,
    ): array {
        return [
            'event_registration_scope' => 'selected',
            'event_registration_ids' => [$registrationId],
            'event_registration_id' => $registrationId,
            'refund_allocations' => [$registrationId => $amount],
            'event_refund' => [
                'event_id' => $eventId,
                'registration_id' => $registrationId,
                'mode' => $mode,
                'actor_type' => $actor->getMorphClass(),
                'actor_id' => (string) $actor->getKey(),
            ],
        ];
    }

    private function normalizeReason(?string $reason, string $mode): string
    {
        $reason = mb_trim((string) $reason);

        if ($mode === 'organizer_approved' && $reason === '') {
            throw new InvalidArgumentException('An organizer refund requires a reason.');
        }

        return mb_substr($reason !== '' ? $reason : 'Refund requested by purchaser.', 0, 500);
    }
}
