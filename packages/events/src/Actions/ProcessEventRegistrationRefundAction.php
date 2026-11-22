<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Contracts\ProviderAwarePaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus as CheckoutPaymentStatus;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventRefundEligibility;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\Integration\CommerceIntegration;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Enums\PaymentStatus as OrderPaymentStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Orchestrates paid event registration refunds via the Orders and Checkout abstractions.
 */
final readonly class ProcessEventRegistrationRefundAction
{
    use AsAction;

    public function __construct(
        private EventRefundEligibility $eligibility,
        private RegistrationServiceInterface $registrations,
        private ?OrderServiceInterface $orders = null,
        private ?PaymentGatewayResolverInterface $paymentGateways = null,
    ) {}

    public function handle(
        Event $event,
        EventRegistration $registration,
        Model $actor,
        bool $organizerOverride = false,
        ?string $reason = null,
        bool $authorize = true,
    ): OrderRefund {
        if (! CommerceIntegration::aiArmadaOrderFulfillmentAvailable()
            || ! interface_exists(PaymentGatewayResolverInterface::class)) {
            throw new RuntimeException('Order and payment gateway integrations are required to refund paid registrations.');
        }

        $registration = $registration->fresh([
            'event',
            'occurrence',
            'session',
            'participants',
            'attendances',
        ]) ?? $registration;

        if ($authorize) {
            $this->authorize($event, $registration, $actor, $organizerOverride);
        }

        $this->assertRefundAllowed($event, $registration, $actor, $organizerOverride);

        $order = $this->resolveOrder($registration);

        if (! $order instanceof Order) {
            throw new InvalidArgumentException('This admission is not connected to a refundable order.');
        }

        if (! $order->isPaid()) {
            throw new InvalidArgumentException('This order has no completed payment to refund.');
        }

        $existing = $this->existingRefund($order, $registration);

        if ($existing instanceof OrderRefund) {
            if ($existing->isFailed()) {
                throw new RuntimeException('The refund request has already failed and requires review.');
            }

            if ($existing->isCompleted()) {
                return $existing;
            }

            $this->markRegistrationRefundPending($registration);

            if ($existing->hasProviderSubmissionStarted()) {
                return $existing->fresh() ?? $existing;
            }
        }

        $orders = $this->orders ?? app(OrderServiceInterface::class);
        $paymentGateways = $this->paymentGateways ?? app(PaymentGatewayResolverInterface::class);

        $amount = $existing instanceof OrderRefund
            ? (int) $existing->amount
            : (int) ($registration->total_amount ?? 0);
        $payment = $this->completedPayment($order);
        $this->assertOnlinePayment($payment);
        $paymentId = $this->paymentId($payment);
        $provider = $this->paymentProvider($payment);
        $processor = $this->resolveProcessor($paymentGateways, $payment);
        $mode = $organizerOverride ? 'organizer_approved' : 'self_service';
        $refundReason = $existing instanceof OrderRefund
            ? $existing->reason
            : $this->normalizeReason($reason, $mode);

        $refund = $existing;

        if (! $refund instanceof OrderRefund) {
            $metadata = $this->refundMetadata($event, $registration, $actor, $mode, $amount, $paymentId, $provider);

            $refund = OwnerContext::withOwner(null, static function () use ($orders, $amount, $metadata, $order, $payment, $refundReason, $registration): OrderRefund {
                $refunds = $orders->createPendingRefund(
                    order: $order,
                    amount: $amount,
                    transactionId: 'event-refund-' . $registration->getKey(),
                    reason: $refundReason,
                    metadata: $metadata,
                    payment: $payment,
                );

                if (count($refunds) !== 1) {
                    throw new RuntimeException('The selected payment did not produce exactly one refund reservation.');
                }

                return $refunds[0];
            });
        }

        if ($refund->isFailed()) {
            throw new RuntimeException('The refund request has already failed and requires review.');
        }

        if ($refund->isCompleted()) {
            return $refund;
        }

        if (! $refund->wasRecentlyCreated) {
            $amount = (int) $refund->amount;
            $refundReason = $refund->reason;
        }

        $this->markRegistrationRefundPending($registration);

        $claimed = OwnerContext::withOwner(null, static fn (): bool => $orders->claimPendingRefundSubmission($refund));

        if (! $claimed) {
            return $refund->fresh() ?? $refund;
        }

        $result = $this->refundPayment($processor, $provider, $paymentId, $amount, $refundReason);

        $this->recordProviderCorrelation($refund, $result);

        if ($this->isProviderConfirmed($result)) {
            OwnerContext::withOwner(null, static function () use ($orders, $refund, $result): void {
                $orders->completePendingRefund(
                    $refund,
                    $result->transactionId ?? $result->paymentId,
                );
            });

            return $refund->fresh() ?? $refund;
        }

        if ($this->isProviderFailed($result)) {
            $this->failRefund($orders, $refund, $result->message ?? 'The payment provider rejected the refund.');

            throw new RuntimeException('The payment provider could not process the refund.');
        }

        return $refund->fresh() ?? $refund;
    }

    private function authorize(
        Event $event,
        EventRegistration $registration,
        Model $actor,
        bool $organizerOverride,
    ): void {
        if ($organizerOverride) {
            Gate::forUser($actor)->authorize('manageAdmissions', $event);

            return;
        }

        Gate::forUser($actor)->authorize('requestRefund', $registration);
    }

    private function assertRefundAllowed(
        Event $event,
        EventRegistration $registration,
        Model $actor,
        bool $organizerOverride,
    ): void {
        if ($organizerOverride) {
            if (! $this->eligibility->canOrganizerRefund($event, $registration)) {
                throw new InvalidArgumentException('This admission is not eligible for an organizer refund.');
            }

            return;
        }

        $reason = $this->eligibility->selfServiceRefundReason($actor, $event, $registration);

        if ($reason !== null) {
            throw new InvalidArgumentException(match ($reason) {
                'refunds_disabled' => 'Refunds are not enabled for this event.',
                'purchaser_only' => 'Only the purchaser can request a refund.',
                'refund_window_closed' => 'The self-service refund window has closed.',
                'admission_already_used' => 'A used admission cannot be refunded online.',
                'free_admission' => 'Free admissions do not have a payment to refund.',
                default => 'This admission is not eligible for a self-service refund.',
            });
        }
    }

    private function resolveOrder(EventRegistration $registration): ?Order
    {
        if ($registration->external_order_id === null
            || $registration->external_order_type !== Order::class) {
            return null;
        }

        return OwnerContext::withOwner(null, static fn (): ?Order => Order::query()
            ->with(['payments', 'refunds'])
            ->whereKey($registration->external_order_id)
            ->first());
    }

    private function existingRefund(Order $order, EventRegistration $registration): ?OrderRefund
    {
        return $order->refunds->first(function (OrderRefund $refund) use ($registration): bool {
            $registrationId = data_get($refund->metadata ?? [], 'event_registration_id');

            return (string) $registrationId === (string) $registration->getKey();
        });
    }

    private function markRegistrationRefundPending(EventRegistration $registration): void
    {
        OwnerContext::withOwner(null, function () use ($registration): void {
            $this->registrations->markRefundPending(
                $registration,
                'Refund is awaiting payment provider confirmation.',
            );
        });
    }

    private function completedPayment(Order $order): OrderPayment
    {
        $payment = $order->payments->first(
            static fn (OrderPayment $candidate): bool => $candidate->status === OrderPaymentStatus::Completed,
        );

        if (! $payment instanceof OrderPayment) {
            throw new InvalidArgumentException('This order has no completed payment to refund.');
        }

        return $payment;
    }

    private function assertOnlinePayment(OrderPayment $payment): void
    {
        if (str_starts_with(mb_strtolower(mb_trim((string) $payment->gateway)), 'offline_')) {
            throw new InvalidArgumentException('Offline admissions must be refunded outside the online payment workflow.');
        }
    }

    private function paymentId(OrderPayment $payment): string
    {
        $paymentId = data_get($payment->metadata ?? [], 'payment_id');

        if (! is_scalar($paymentId) || mb_trim((string) $paymentId) === '') {
            throw new InvalidArgumentException('The completed payment has no provider reference for refunding.');
        }

        return mb_trim((string) $paymentId);
    }

    private function resolveProcessor(PaymentGatewayResolverInterface $paymentGateways, OrderPayment $payment): PaymentProcessorInterface
    {
        $identifier = mb_trim((string) $payment->gateway);

        if ($identifier !== '' && $paymentGateways->hasGateway($identifier)) {
            return $paymentGateways->resolve($identifier);
        }

        throw new RuntimeException("The payment processor [{$identifier}] is no longer available.");
    }

    private function paymentProvider(OrderPayment $payment): ?string
    {
        $provider = data_get($payment->metadata ?? [], 'provider');

        return is_string($provider) && mb_trim($provider) !== '' ? mb_trim($provider) : null;
    }

    private function refundPayment(
        PaymentProcessorInterface $processor,
        ?string $provider,
        string $paymentId,
        int $amount,
        string $reason,
    ): PaymentResult {
        if ($provider !== null && $processor instanceof ProviderAwarePaymentProcessorInterface) {
            return $processor->refundForProvider($provider, $paymentId, $amount, $reason);
        }

        return $processor->refund($paymentId, $amount, $reason);
    }

    private function isProviderConfirmed(PaymentResult $result): bool
    {
        return in_array($result->status, [
            CheckoutPaymentStatus::Completed,
            CheckoutPaymentStatus::Refunded,
            CheckoutPaymentStatus::PartiallyRefunded,
        ], true);
    }

    private function isProviderFailed(PaymentResult $result): bool
    {
        return in_array($result->status, [
            CheckoutPaymentStatus::Failed,
            CheckoutPaymentStatus::Cancelled,
        ], true);
    }

    private function failRefund(OrderServiceInterface $orders, OrderRefund $refund, string $reason): void
    {
        OwnerContext::withOwner(null, static fn (): OrderRefund => $orders->failPendingRefund(
            $refund,
            mb_substr(mb_trim($reason) !== '' ? mb_trim($reason) : 'Refund failed.', 0, 500),
        ));
    }

    private function recordProviderCorrelation(OrderRefund $refund, PaymentResult $result): void
    {
        $providerRefundId = $result->transactionId;

        if ($providerRefundId === null || mb_trim($providerRefundId) === '') {
            return;
        }

        $metadata = is_array($refund->metadata) ? $refund->metadata : [];
        $originalPaymentId = data_get($metadata, 'payment_id');

        if ($providerRefundId === (string) $originalPaymentId) {
            return;
        }

        $metadata['provider_refund_id'] = mb_trim($providerRefundId);

        OwnerContext::withOwner(null, static function () use ($metadata, $refund): void {
            $refund->forceFill(['metadata' => $metadata])->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function refundMetadata(
        Event $event,
        EventRegistration $registration,
        Model $actor,
        string $mode,
        int $amount,
        string $paymentId,
        ?string $provider,
    ): array {
        return [
            'event_registration_scope' => 'selected',
            'event_registration_ids' => [(string) $registration->getKey()],
            'event_registration_id' => (string) $registration->getKey(),
            'refund_allocations' => [(string) $registration->getKey() => $amount],
            'payment_id' => $paymentId,
            'provider' => $provider,
            'event_refund' => [
                'event_id' => (string) $event->getKey(),
                'registration_id' => (string) $registration->getKey(),
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
