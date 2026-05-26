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
        $brandId = $payload['brand_id'] ?? data_get($payload, 'purchase.brand_id');

        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            $owner = ChipWebhookOwnerResolver::resolveFromPayload($payload);

            if ($owner === null) {
                $brandIdMap = config('chip.owner.webhook_brand_id_map', []);

                Log::channel(config('chip.logging.channel', 'stack'))
                    ->warning('CHIP webhook blocked because owner scoping is enabled at runtime, but no owner could be resolved from the webhook brand_id', [
                        'event_type' => $eventType,
                        'brand_id' => is_string($brandId) ? $brandId : null,
                        'owner_scoping_enabled' => true,
                        'brand_id_map_entries' => is_array($brandIdMap) ? count($brandIdMap) : 0,
                        'hint' => 'If CHIP_OWNER_ENABLED was recently changed, clear and rebuild the cached config on this server.',
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
