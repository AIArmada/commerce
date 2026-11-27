<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Features\Collect\Api\RefundStateSynchronizer;

/**
 * Handles documented CHIP refund completion webhooks.
 */
class PurchaseRefundedHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $localPurchaseId = $payload->localPurchaseId;

        if ($localPurchaseId === null) {
            return WebhookResult::skipped('Purchase not found locally');
        }

        if (! $this->hasRefundPaymentPayload($payload->rawPayload)) {
            return WebhookResult::skipped('Refund payment payload is missing');
        }

        $paymentRefunded = PaymentRefunded::fromPayload($payload->rawPayload);

        if ($paymentRefunded->payment === null) {
            return WebhookResult::skipped('Refund payment payload is missing');
        }

        app(RefundStateSynchronizer::class)->handle($paymentRefunded->payment, $localPurchaseId);

        PaymentRefunded::dispatch($paymentRefunded->payment, $payload->rawPayload);

        return WebhookResult::handled("Purchase {$localPurchaseId} refund state synchronized");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasRefundPaymentPayload(array $payload): bool
    {
        return ($payload['type'] ?? null) === 'payment'
            && data_get($payload, 'related_to.type') === 'purchase'
            && is_string(data_get($payload, 'related_to.id'))
            && is_array($payload['payment'] ?? null)
            && is_numeric(data_get($payload, 'payment.amount'))
            && is_string(data_get($payload, 'payment.currency'))
            && data_get($payload, 'payment.currency') !== '';
    }
}
