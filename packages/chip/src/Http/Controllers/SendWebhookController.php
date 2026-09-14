<?php

declare(strict_types=1);

namespace AIArmada\Chip\Http\Controllers;

use AIArmada\Chip\Events\SendWebhookReceived;
use AIArmada\Chip\Exceptions\WebhookVerificationException;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use JsonException;

final class SendWebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhookService) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            if (! $this->webhookService->verifySendSignature($request)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            /** @var mixed $decodedPayload */
            $decodedPayload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (WebhookVerificationException $exception) {
            return response()->json(['error' => $exception->getMessage()], 401);
        } catch (JsonException) {
            return response()->json(['error' => 'Invalid JSON payload'], 400);
        }

        if (! is_array($decodedPayload) || array_is_list($decodedPayload)) {
            return response()->json(['error' => 'The webhook payload must be a JSON object'], 400);
        }

        /** @var array<string, mixed> $payload */
        $payload = $decodedPayload;

        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            $owner = ChipWebhookOwnerResolver::resolveSendOwner();

            if ($owner === null) {
                Log::channel(config('chip.logging.channel', 'stack'))
                    ->warning('CHIP Send webhook received but no owner could be resolved', [
                        'id' => $payload['id'] ?? null,
                    ]);

                return response()->json(['error' => 'Owner resolution failed'], 500);
            }

            OwnerContext::withOwner($owner, static function () use ($payload): void {
                SendWebhookReceived::dispatch($payload);
            });

            return response()->json(['status' => 'received']);
        }

        SendWebhookReceived::dispatch($payload);

        return response()->json(['status' => 'received']);
    }
}
