<?php

declare(strict_types=1);

namespace AIArmada\Chip\Gateways;

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;
use AIArmada\CommerceSupport\Exceptions\WebhookVerificationException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

/**
 * CHIP webhook handler implementing the universal WebhookHandlerInterface.
 */
final class ChipWebhookHandler implements WebhookHandlerInterface
{
    public function __construct(
        private WebhookService $webhookService,
        private ChipCollectService $collectService,
    ) {}

    public function verifyWebhook(Request $request): bool
    {
        try {
            return $this->webhookService->verifySignature($request);
        } catch (\AIArmada\Chip\Exceptions\WebhookVerificationException $e) {
            throw new WebhookVerificationException(
                message: $e->getMessage(),
                gatewayName: 'chip'
            );
        }
    }

    public function parseWebhook(Request $request): WebhookPayload
    {
        $payload = $this->webhookService->parsePayload($request->getContent());
        $data = (array) $payload;

        $status = $this->mapChipStatus($data['status'] ?? 'unknown');
        $paymentId = $this->resolvePurchaseIdFromWebhook($data) ?? ($data['id'] ?? '');

        return new WebhookPayload(
            eventType: $this->getEventType($request),
            paymentId: $paymentId,
            status: $status,
            reference: $data['reference'] ?? null,
            gatewayName: 'chip',
            occurredAt: isset($data['updated_on'])
                ? CarbonImmutable::createFromTimestamp($data['updated_on'])
                : CarbonImmutable::now(),
            rawData: $data
        );
    }

    public function getEventType(Request $request): string
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return 'unknown';
        }

        $eventType = $payload['event_type'] ?? $payload['event'] ?? null;

        if (is_string($eventType) && $eventType !== '') {
            return $eventType;
        }

        $status = $payload['status'] ?? 'unknown';

        return match ($status) {
            'paid' => 'payment.paid',
            'captured', 'paid_authorized', 'recurring_successful', 'cleared', 'settled' => 'payment.paid',
            'refunded' => 'payment.refunded',
            'partially_refunded' => 'payment.partially_refunded',
            'cancelled' => 'payment.cancelled',
            'released' => 'payment.cancelled',
            'error', 'blocked' => 'payment.failed',
            'chargeback' => 'payment.disputed',
            'hold', 'preauthorized' => 'payment.authorized',
            'pending_execute', 'pending_charge', 'sent', 'viewed', 'attempted_capture', 'attempted_refund', 'attempted_recurring' => 'payment.pending',
            'pending_refund' => 'purchase.pending_refund',
            'expired', 'overdue' => 'payment.expired',
            default => "payment.{$status}",
        };
    }

    public function isPaymentEvent(Request $request): bool
    {
        // CHIP webhooks are always payment-related
        return true;
    }

    public function getPaymentFromWebhook(Request $request): ?PaymentIntentInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! isset($payload['id'])) {
            return null;
        }

        try {
            $purchaseId = $this->resolvePurchaseIdFromWebhook($payload);

            if ($purchaseId !== null) {
                $purchase = $this->collectService->getPurchase($purchaseId);

                return new ChipPaymentIntent($purchase);
            }

            // We can construct a Purchase directly from webhook data
            $purchase = PurchaseData::from($payload);

            return new ChipPaymentIntent($purchase);
        } catch (Throwable) {
            // If parsing fails, try fetching from API
            try {
                $purchaseId = $this->resolvePurchaseIdFromWebhook($payload) ?? $payload['id'];
                $purchase = $this->collectService->getPurchase($purchaseId);

                return new ChipPaymentIntent($purchase);
            } catch (Throwable) {
                return null;
            }
        }
    }

    /**
     * Map CHIP status to universal PaymentStatus.
     */
    private function mapChipStatus(string $chipStatus): PaymentStatus
    {
        return match ($chipStatus) {
            'created' => PaymentStatus::CREATED,
            'sent', 'viewed', 'pending_execute', 'pending_charge' => PaymentStatus::PENDING,
            'attempted_capture', 'attempted_refund', 'attempted_recurring', 'pending_refund' => PaymentStatus::PROCESSING,
            'pending_capture' => PaymentStatus::AUTHORIZED,
            'pending_release' => PaymentStatus::AUTHORIZED,
            'hold' => PaymentStatus::AUTHORIZED,
            'preauthorized' => PaymentStatus::AUTHORIZED,
            'paid', 'captured', 'paid_authorized', 'recurring_successful', 'cleared', 'settled' => PaymentStatus::PAID,
            'refunded' => PaymentStatus::REFUNDED,
            'partially_refunded' => PaymentStatus::PARTIALLY_REFUNDED,
            'cancelled', 'released' => PaymentStatus::CANCELLED,
            'expired', 'overdue' => PaymentStatus::EXPIRED,
            'chargeback' => PaymentStatus::DISPUTED,
            'error' => PaymentStatus::FAILED,
            'blocked' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePurchaseIdFromWebhook(array $payload): ?string
    {
        if (($payload['type'] ?? null) === 'payment' && data_get($payload, 'related_to.type') === 'purchase') {
            $purchaseId = data_get($payload, 'related_to.id');

            return is_string($purchaseId) && $purchaseId !== '' ? $purchaseId : null;
        }

        return null;
    }
}
