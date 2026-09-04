<?php

declare(strict_types=1);

namespace AIArmada\Chip\Http\Controllers;

use AIArmada\Chip\Events\SendWebhookReceived;
use AIArmada\Chip\Exceptions\WebhookVerificationException;
use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        SendWebhookReceived::dispatch($payload);

        return response()->json(['status' => 'received']);
    }
}
