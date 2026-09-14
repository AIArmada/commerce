<?php

declare(strict_types=1);

namespace AIArmada\Chip\Http\Controllers;

use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use LogicException;
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

        $routeName = $request->route()?->getName() ?: 'chip.webhook';
        /** @var WebhookConfigRepository $configRepository */
        $configRepository = app(WebhookConfigRepository::class);
        $config = $configRepository->getConfig($routeName);

        if ($config === null) {
            throw InvalidConfig::couldNotFindConfig($routeName);
        }

        // Verify the signature before touching owner resolution so unknown
        // brand ids cannot be probed: unauthenticated callers always see 401.
        if (! $config->signatureValidator->isValid($request, $config)) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->warning('CHIP webhook signature verification failed', [
                    'event_type' => $eventType,
                ]);

            return response()->json([
                'error' => 'Invalid signature',
            ], 401);
        }

        $owner = null;

        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            $owner = ChipWebhookOwnerResolver::resolveFromPayload($payload);

            if ($owner === null) {
                Log::channel(config('chip.logging.channel', 'stack'))
                    ->warning('CHIP webhook received but no owner could be resolved for brand_id', [
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

        $processor = function () use ($request, $config): JsonResponse {
            $response = (new WebhookProcessor($request, $config))->process();

            if (! $response instanceof JsonResponse) {
                throw new LogicException('CHIP webhook response must be a JSON response.');
            }

            return $response;
        };

        if ($owner instanceof Model) {
            return OwnerContext::withOwner($owner, $processor);
        }

        $response = $processor();

        /** @var JsonResponse $response */
        return $response;
    }
}
