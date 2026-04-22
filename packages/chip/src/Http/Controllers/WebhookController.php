<?php

declare(strict_types=1);

namespace AIArmada\Chip\Http\Controllers;

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Services\WebhookEventDispatcher;
use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        private WebhookEventDispatcher $dispatcher,
    ) {}

    /**
     * Handle incoming CHIP webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventType = $payload['event_type'] ?? 'unknown';

        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            $owner = ChipWebhookOwnerResolver::resolveFromPayload($payload);

            if ($owner === null) {
                Log::channel(config('chip.logging.channel', 'stack'))
                    ->error('CHIP webhook received but no owner could be resolved for brand_id', [
                        'event_type' => $eventType,
                        'brand_id' => $payload['brand_id'] ?? null,
                    ]);

                return response()->json([
                    'error' => 'Owner resolution failed',
                ], 500);
            }

            return OwnerContext::withOwner($owner, fn (): JsonResponse => $this->handleScoped($eventType, $payload));
        }

        return $this->handleScoped($eventType, $payload);
    }

    /**
     * Generate a unique idempotency key from the webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function generateIdempotencyKey(array $payload): string
    {
        $components = [
            $payload['event_type'] ?? 'unknown',
            $payload['id'] ?? '',
            $payload['status'] ?? '',
            $payload['updated_on'] ?? $payload['created_on'] ?? '',
        ];

        if ((bool) config('chip.owner.enabled', false)) {
            $owner = OwnerContext::resolve();

            if ($owner !== null) {
                $components[] = $owner->getMorphClass();
                $components[] = (string) $owner->getKey();
            }
        }

        return hash('sha256', implode(':', $components));
    }

    /**
     * Check if this webhook has already been processed.
     */
    private function isDuplicateWebhook(string $idempotencyKey): bool
    {
        if (! config('chip.webhooks.deduplication', true)) {
            return false;
        }

        $webhook = $this->findWebhookByIdempotencyKey($idempotencyKey);

        return $webhook !== null
            && ($webhook->processed || $webhook->status === 'processed');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleScoped(string $eventType, array $payload): JsonResponse
    {
        $idempotencyKey = $this->generateIdempotencyKey($payload);

        if ($this->isDuplicateWebhook($idempotencyKey)) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->info('CHIP webhook skipped - duplicate', [
                    'idempotency_key' => $idempotencyKey,
                    'event_type' => $eventType,
                ]);

            return response()->json([
                'status' => 'ok',
                'message' => 'Duplicate webhook ignored',
            ]);
        }

        $startTime = microtime(true);

        $webhook = $this->storeWebhookRecord($eventType, $payload, $idempotencyKey);

        try {
            // Dispatch the generic WebhookReceived event
            WebhookReceived::dispatch(
                $eventType,
                $payload,
                $this->dispatcher->extractPurchase($payload),
                $this->dispatcher->extractPayout($payload),
                $this->dispatcher->extractBillingTemplateClient($payload),
            );

            // Dispatch the specific typed event using the centralized dispatcher
            $this->dispatcher->dispatch($eventType, $payload);

            // Mark as processed
            $processingTime = (microtime(true) - $startTime) * 1000;
            $webhook?->markProcessed($processingTime);

            return response()->json([
                'status' => 'ok',
                'event_type' => $eventType,
            ]);
        } catch (Throwable $e) {
            $webhook?->markFailed($e);

            throw $e;
        }
    }

    /**
     * Store webhook record for tracking and deduplication.
     *
     * @param  array<string, mixed>  $payload
     */
    private function storeWebhookRecord(string $eventType, array $payload, string $idempotencyKey): ?Webhook
    {
        if (! config('chip.webhooks.store_webhooks', true)) {
            return null;
        }

        return Webhook::query()
            ->forOwner()
            ->updateOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'event_type' => $eventType,
                    'event' => $eventType,
                    'payload' => $payload,
                    'status' => 'pending',
                    'verified' => true,
                    'processed' => false,
                    'processed_at' => null,
                    'last_error' => null,
                    'processing_time_ms' => null,
                    'title' => 'Incoming: ' . $eventType,
                    'events' => [$eventType],
                    'callback' => request()->url(),
                    'created_on' => $payload['created_on'] ?? time(),
                    'updated_on' => $payload['updated_on'] ?? time(),
                ]
            );
    }

    private function findWebhookByIdempotencyKey(string $idempotencyKey): ?Webhook
    {
        return Webhook::query()
            ->forOwner()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }
}
