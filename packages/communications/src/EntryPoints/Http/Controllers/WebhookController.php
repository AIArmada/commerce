<?php

declare(strict_types=1);

namespace AIArmada\Communications\EntryPoints\Http\Controllers;

use AIArmada\Communications\Contracts\ProviderWebhookRegistrar;
use AIArmada\Communications\Contracts\WebhookOwnerResolver;
use AIArmada\Communications\EntryPoints\Jobs\ProcessWebhookEventJob;
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

        $maxBytes = max(1, (int) config('communications.webhooks.max_payload_bytes', 262144));

        if (mb_strlen($request->getContent(), '8bit') > $maxBytes) {
            abort(413, 'Webhook payload exceeds the maximum allowed size.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()?->all() ?? [];

        $maxDepth = max(1, (int) config('communications.webhooks.max_payload_depth', 32));

        if ($this->payloadExceedsDepth($payload, $maxDepth)) {
            abort(413, 'Webhook payload exceeds the maximum allowed depth.');
        }

        unset($payload['__owner_id'], $payload['__owner_type']);

        $owner = $ownerResolver->resolve($provider, $payload);

        ProcessWebhookEventJob::dispatch(
            provider: $provider,
            payload: $payload,
            ownerId: $owner?->id,
            ownerType: $owner?->type,
            signatureValidatedAt: $request->attributes->get('communications.webhook_signature_validated_at'),
        );

        return response()->json(['status' => 'accepted'], 202);
    }

    private function payloadExceedsDepth(array $payload, int $maxDepth): bool
    {
        $stack = [[$payload, 0]];

        while ($stack !== []) {
            [$value, $depth] = array_pop($stack);

            if ($depth > $maxDepth) {
                return true;
            }

            foreach ($value as $item) {
                if (is_array($item)) {
                    $stack[] = [$item, $depth + 1];
                }
            }
        }

        return false;
    }
}
