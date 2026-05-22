<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Actions\Purchases\SyncPurchaseRefundState;
use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Events\PaymentRefunded;

/**
 * Handles documented CHIP refund completion webhooks.
 */
class PurchaseRefundedHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $localPurchase = $payload->localPurchase;

        if ($localPurchase === null) {
            return WebhookResult::skipped('Purchase not found locally');
        }

        if (! $this->hasRefundPaymentPayload($payload->rawPayload)) {
            return WebhookResult::skipped('Refund payment payload is missing');
        }

        $paymentRefunded = PaymentRefunded::fromPayload($payload->rawPayload);

        if ($paymentRefunded->payment === null) {
            return WebhookResult::skipped('Refund payment payload is missing');
        }

        app(SyncPurchaseRefundState::class)->handle($paymentRefunded->payment, $localPurchase);

        PaymentRefunded::dispatch($paymentRefunded->payment, $payload->rawPayload);

        $localPurchase->refresh();

        return WebhookResult::handled("Purchase {$localPurchase->id} refund state synchronized");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasRefundPaymentPayload(array $payload): bool
    {
        if (isset($payload['payment']) && is_array($payload['payment'])) {
            return isset($payload['payment']['amount'], $payload['payment']['currency']);
        }

        return isset($payload['amount'], $payload['currency']);
    }
}
