<?php

declare(strict_types=1);

namespace AIArmada\Communications\Http\Controllers;

use AIArmada\Communications\Jobs\ProcessWebhookEventJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WebhookController extends Controller
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()?->all() ?? [];

        $ownerId = $payload['__owner_id'] ?? null;
        $ownerType = $payload['__owner_type'] ?? null;

        ProcessWebhookEventJob::dispatch(
            provider: $provider,
            payload: $payload,
            ownerId: is_string($ownerId) ? $ownerId : null,
            ownerType: is_string($ownerType) ? $ownerType : null,
        );

        return response()->json(['status' => 'accepted'], 202);
    }
}
