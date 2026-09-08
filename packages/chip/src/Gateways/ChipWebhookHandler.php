<?php

declare(strict_types=1);

namespace AIArmada\Chip\Gateways;

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\Chip\Support\ChipPaymentStatusMapper;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentIntentInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookHandlerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\WebhookPayload;
use AIArmada\CommerceSupport\Exceptions\WebhookVerificationException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use InvalidArgumentException;
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
        $eventType = $this->getEventType($request);

        if (WebhookEventType::tryFrom($eventType) === null) {
            throw new InvalidArgumentException('CHIP webhook payload contains an unsupported event_type.');
        }

        $status = ChipPaymentStatusMapper::mapWebhook(
            is_string($data['status'] ?? null) ? $data['status'] : null,
            $eventType,
        );
        $paymentId = $this->resolvePurchaseIdFromWebhook($data) ?? ($data['id'] ?? '');

        return new WebhookPayload(
            eventType: $eventType,
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

        $eventType = $payload['event_type'] ?? null;

        if (is_string($eventType) && $eventType !== '') {
            return $eventType;
        }

        return 'unknown';
    }

    public function isPaymentEvent(Request $request): bool
    {
        $eventType = WebhookEventType::tryFrom($this->getEventType($request));

        return $eventType?->isPurchaseEvent() === true || $eventType?->isPaymentEvent() === true;
    }

    public function getPaymentFromWebhook(Request $request): ?PaymentIntentInterface
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! isset($payload['id'])) {
            return null;
        }

        $eventType = WebhookEventType::tryFrom($this->getEventType($request));
        $type = $payload['type'] ?? null;

        if ($eventType === null || ! in_array($type, ['purchase', 'payment'], true)) {
            return null;
        }

        if ($eventType->isPurchaseEvent() && $type !== 'purchase') {
            return null;
        }

        if ($eventType->isPaymentEvent() && $type !== 'payment') {
            return null;
        }

        if (! $eventType->isPurchaseEvent() && ! $eventType->isPaymentEvent()) {
            return null;
        }

        try {
            $purchaseId = $this->resolvePurchaseIdFromWebhook($payload);

            if ($purchaseId !== null) {
                $purchase = $this->collectService->getPurchase($purchaseId);

                return new ChipPaymentIntent($purchase);
            }

            if ($eventType->isPurchaseEvent()) {
                return new ChipPaymentIntent(PurchaseData::from($payload));
            }

            return null;
        } catch (Throwable) {
            return null;
        }
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
