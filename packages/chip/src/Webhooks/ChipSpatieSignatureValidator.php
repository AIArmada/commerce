<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Throwable;

final class ChipSpatieSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $shouldVerify = (bool) config('chip.webhooks.verify_signature', true);

        if (! $shouldVerify) {
            if (app()->environment('production')) {
                Log::channel(config('chip.logging.channel', 'stack'))
                    ->error('CHIP webhook signature verification cannot be disabled in production');

                return false;
            }

            return true;
        }

        $bodyHash = hash('sha256', $request->getContent());
        $cached = $request->attributes->get('chip_webhook_signature_validation');

        if (is_array($cached)
            && is_string($cached['body_hash'] ?? null)
            && hash_equals($bodyHash, $cached['body_hash'])
            && is_bool($cached['valid'] ?? null)) {
            return $cached['valid'];
        }

        /** @var WebhookService $webhookService */
        $webhookService = app(WebhookService::class);

        try {
            $valid = $webhookService->verifySignature($request);
        } catch (Throwable $exception) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->warning('CHIP webhook signature validation failed', [
                    'error' => $exception->getMessage(),
                ]);

            $valid = false;
        }

        $request->attributes->set('chip_webhook_signature_validation', [
            'body_hash' => $bodyHash,
            'valid' => $valid,
        ]);

        return $valid;
    }
}
