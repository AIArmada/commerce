<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

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

        /** @var WebhookService $webhookService */
        $webhookService = app(WebhookService::class);

        try {
            return $webhookService->verifySignature($request);
        } catch (Throwable $exception) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->warning('CHIP webhook signature validation failed', [
                    'error' => $exception->getMessage(),
                ]);

            return false;
        }
    }
}
