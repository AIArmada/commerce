<?php

declare(strict_types=1);

namespace AIArmada\Chip\Gateways;

use AIArmada\Chip\DataObjects\Purchase;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;
use AIArmada\CommerceSupport\Exceptions\WebhookVerificationException;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

        return new WebhookPayload(
            eventType: $this->getEventType($request),
            paymentId: $data['id'] ?? '',
            status: $status,
            reference: $data['reference'] ?? null,
            gatewayName: 'chip',
            occurredAt: isset($data['updated_on'])
                ? Carbon::createFromTimestamp($data['updated_on'])
                : Carbon::now(),
            rawData: $data
        );
    }

    public function getEventType(Request $request): string
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return 'unknown';
        }

        $status = $payload['status'] ?? 'unknown';

        // CHIP doesn't have event types like Stripe, but we can infer from status
        return match ($status) {
            'paid' => 'payment.paid',
            'refunded' => 'payment.refunded',
            'cancelled' => 'payment.cancelled',
            'error', 'blocked' => 'payment.failed',
            'hold', 'preauthorized' => 'payment.authorized',
            'pending_execute', 'pending_charge' => 'payment.pending',
            'pending_refund' => 'refund.pending',
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
            // We can construct a Purchase directly from webhook data
            $purchase = Purchase::fromArray($payload);

            return new ChipPaymentIntent($purchase);
        } catch (\Throwable) {
            // If parsing fails, try fetching from API
            try {
                $purchase = $this->collectService->getPurchase($payload['id']);

                return new ChipPaymentIntent($purchase);
            } catch (\Throwable) {
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
            'pending_execute' => PaymentStatus::PENDING,
            'pending_charge' => PaymentStatus::PENDING,
            'pending_capture' => PaymentStatus::AUTHORIZED,
            'pending_release' => PaymentStatus::AUTHORIZED,
            'pending_refund' => PaymentStatus::PROCESSING,
            'hold' => PaymentStatus::AUTHORIZED,
            'preauthorized' => PaymentStatus::AUTHORIZED,
            'paid' => PaymentStatus::PAID,
            'refunded' => PaymentStatus::REFUNDED,
            'partially_refunded' => PaymentStatus::PARTIALLY_REFUNDED,
            'cancelled' => PaymentStatus::CANCELLED,
            'expired' => PaymentStatus::EXPIRED,
            'error' => PaymentStatus::FAILED,
            'blocked' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
