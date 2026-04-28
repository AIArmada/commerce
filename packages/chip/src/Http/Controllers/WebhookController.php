<?php

declare(strict_types=1);

namespace AIArmada\Chip\Http\Controllers;

use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Exceptions\InvalidConfig;
use Spatie\WebhookClient\WebhookConfigRepository;
use Spatie\WebhookClient\WebhookProcessor;

class WebhookController extends Controller
{
    /**
     * Handle incoming CHIP webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
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

            $payload['__owner_type'] = $owner->getMorphClass();
            $payload['__owner_id'] = (string) $owner->getKey();
            $request->replace($payload);
        }

        $routeName = $request->route()?->getName() ?: 'chip.webhook';
        /** @var WebhookConfigRepository $configRepository */
        $configRepository = app(WebhookConfigRepository::class);
        $config = $configRepository->getConfig($routeName);

        if ($config === null) {
            throw InvalidConfig::couldNotFindConfig($routeName);
        }

        $response = (new WebhookProcessor($request, $config))->process();

        /** @var JsonResponse $response */
        return $response;
    }
}
