<?php

declare(strict_types=1);

namespace AIArmada\Communications\Http\Controllers;

use AIArmada\Communications\Contracts\WebhookOwnerResolver;
use AIArmada\Communications\Jobs\ProcessWebhookEventJob;
use AIArmada\Communications\Webhooks\Contracts\ProviderWebhookRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WebhookController extends Controller
{
    public function handle(
        Request $request,
        string $provider,
        WebhookOwnerResolver $ownerResolver,
        ProviderWebhookRegistrar $registrar,
    ): JsonResponse {
        $provider = $registrar->normalizeProvider($provider);

        /** @var array<string, mixed> $payload */
        $payload = $request->json()?->all() ?? [];

        unset($payload['__owner_id'], $payload['__owner_type']);

        $owner = $ownerResolver->resolve($provider, $payload);

        ProcessWebhookEventJob::dispatch(
            provider: $provider,
            payload: $payload,
            ownerId: $owner !== null ? (string) $owner->getKey() : null,
            ownerType: $owner?->getMorphClass(),
            signatureValidatedAt: $request->attributes->get('communications.webhook_signature_validated_at'),
        );

        return response()->json(['status' => 'accepted'], 202);
    }
}
