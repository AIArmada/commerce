<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Webhooks;

use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;

final class CheckoutSpatieSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        if (! (bool) config('checkout.webhooks.verify_signature', true)) {
            if (app()->environment('production')) {
                Log::channel(config('checkout.webhooks.log_channel') ?? config('logging.default'))
                    ->error('Checkout webhook signature verification cannot be disabled in production');

                return false;
            }

            return true;
        }

        $gateway = $this->detectGateway($request);

        return match ($gateway) {
            'chip' => $this->verifyChipSignature($request),
            'stripe' => $this->verifyStripeSignature($request),
            default => false,
        };
    }

    private function detectGateway(Request $request): ?string
    {
        if ($request->hasHeader('X-Signature')) {
            return 'chip';
        }

        if ($request->hasHeader('Stripe-Signature')) {
            return 'stripe';
        }

        if ($request->has('reference') && $request->has('status')) {
            return 'chip';
        }

        if ($request->input('data.object') !== null && $request->has('type')) {
            return 'stripe';
        }

        return null;
    }

    private function verifyChipSignature(Request $request): bool
    {
        if (! class_exists(WebhookService::class)) {
            return false;
        }

        /** @var WebhookService $webhookService */
        $webhookService = app(WebhookService::class);

        try {
            return $webhookService->verifySignature($request);
        } catch (Throwable) {
            return false;
        }
    }

    private function verifyStripeSignature(Request $request): bool
    {
        if (! class_exists(Webhook::class)) {
            return false;
        }

        $signature = $request->header('Stripe-Signature');
        $secret = config('cashier.webhook.secret');

        if (! is_string($signature) || $signature === '' || ! is_string($secret) || $secret === '') {
            return false;
        }

        try {
            Webhook::constructEvent($request->getContent(), $signature, $secret);

            return true;
        } catch (SignatureVerificationException) {
            return false;
        }
    }
}
