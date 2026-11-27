<?php

declare(strict_types=1);

namespace AIArmada\Jnt\EntryPoints\Jobs;

use AIArmada\CommerceSupport\Features\Webhooks\EntryPoints\CommerceWebhookProcessor;
use AIArmada\Jnt\Features\Tracking\Actions\ProcessTrackingWebhook;
use Spatie\WebhookClient\Models\WebhookCall;

/**
 * Process J&T Express webhook events.
 *
 * This job handles incoming J&T tracking updates and dispatches events.
 */
class ProcessJntWebhook extends CommerceWebhookProcessor
{
    public int $tries = 1;

    public function __construct(WebhookCall $webhookCall)
    {
        parent::__construct($webhookCall);

        $this->tries = max(1, (int) config('jnt.webhooks.retry_times', 3));
    }

    public function backoff(): int
    {
        return max(1, (int) config('jnt.webhooks.retry_backoff_seconds', 60));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventId(array $payload): ?string
    {
        $eventId = parent::extractEventId($payload);

        if ($eventId !== null) {
            return $eventId;
        }

        $biz = $this->decodeBizContent($payload);

        if ($biz === null) {
            return null;
        }

        $canonicalPayload = json_encode($biz, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($canonicalPayload)) {
            return null;
        }

        return 'jnt:' . hash('sha256', $canonicalPayload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayloadForLogging(array $payload): array
    {
        $sanitized = [
            'payload_keys' => array_keys($payload),
        ];

        $bizContent = $payload['bizContent'] ?? null;

        if (is_string($bizContent) && $bizContent !== '') {
            $sanitized['bizContent_length'] = mb_strlen($bizContent);
            $sanitized['bizContent_sha256'] = hash('sha256', $bizContent);
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function decodeBizContent(array $payload): ?array
    {
        $bizContent = $payload['bizContent'] ?? null;

        if (! is_string($bizContent) || $bizContent === '') {
            return null;
        }

        $decoded = json_decode($bizContent, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function extractEventType(array $payload): string
    {
        $biz = $this->decodeBizContent($payload);

        if ($biz === null) {
            $scanType = $payload['scantype'] ?? null;

            return is_string($scanType) && $scanType !== '' ? $scanType : 'tracking.update';
        }

        $details = $biz['details'] ?? null;

        if (is_array($details) && $details !== []) {
            $last = end($details);

            if (is_array($last)) {
                $scanType = $last['scanType'] ?? $last['scanTypeCode'] ?? null;

                if (is_string($scanType) && $scanType !== '') {
                    return $scanType;
                }
            }
        }

        $scanType = $payload['scantype'] ?? null;

        return is_string($scanType) && $scanType !== '' ? $scanType : 'tracking.update';
    }

    /** @param array<string, mixed> $payload */
    protected function processEvent(string $eventType, array $payload): void
    {
        (new ProcessTrackingWebhook($this->webhookCall))->execute(
            $eventType,
            $payload,
            $this->decodeBizContent($payload),
            $this->sanitizePayloadForLogging($payload),
        );
    }
}
