<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks\Handlers;

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Enums\PurchaseStatus;
use AIArmada\Chip\Events\PurchasePaymentFailure;

/**
 * Handles the documented purchase.payment_failure webhook event.
 */
class PaymentFailedHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $localPurchase = $payload->localPurchase;

        if ($localPurchase === null) {
            return WebhookResult::skipped('Purchase not found locally');
        }

        $failureReason = $this->failureReason(
            $payload->get('transaction_data.attempts'),
            $payload->get('error_code'),
        );

        // Update local status
        $localPurchase->forceFill([
            'status' => PurchaseStatus::ERROR,
            'failure_reason' => $failureReason,
        ])->save();

        // Dispatch Laravel event
        PurchasePaymentFailure::dispatch(
            PurchaseData::from($payload->rawPayload),
            $payload->rawPayload,
        );

        return WebhookResult::handled("Purchase {$localPurchase->id} marked as failed");
    }

    private function failureReason(mixed $attempts, mixed $errorCode): string
    {
        // Attempts arrive newest-first; the first usable error wins.
        if (is_array($attempts)) {
            foreach ($attempts as $attempt) {
                if (! is_array($attempt) || ! is_array($attempt['error'] ?? null)) {
                    continue;
                }

                $error = $attempt['error'];
                $message = $error['message'] ?? null;

                if (is_string($message) && $message !== '') {
                    return $message;
                }

                $code = $error['code'] ?? null;

                if (is_string($code) && $code !== '') {
                    return $code;
                }
            }
        }

        if (is_string($errorCode) && $errorCode !== '') {
            return $errorCode;
        }

        return 'Unknown payment failure';
    }
}
