<?php

declare(strict_types=1);

namespace AIArmada\Chip\Http\Controllers;

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Services\WebhookEventDispatcher;
use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

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
     * @param  array<string, mixed>  $payload
     */
    private function handleScoped(string $eventType, array $payload): JsonResponse
    {
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

        return response()->json([
            'status' => 'ok',
            'event_type' => $eventType,
        ]);
    }
}
