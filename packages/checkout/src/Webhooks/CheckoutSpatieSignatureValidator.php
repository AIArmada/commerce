<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Webhooks;

use AIArmada\Checkout\Support\CheckoutPaymentReference;
use AIArmada\Chip\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Stripe\Webhook;
use Throwable;

final class CheckoutSpatieSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $gateway = $this->resolveGateway($request, $config);

        if ($gateway === null || ! $this->hasKnownPayloadShape($request, $gateway)) {
            return false;
        }

        if (! (bool) config('checkout.webhooks.verify_signature', true)) {
            if (app()->environment('production')) {
                Log::channel(config('checkout.webhooks.log_channel') ?? config('logging.default'))
                    ->error('Checkout webhook signature verification cannot be disabled in production');

                return false;
            }

            return true;
        }

        return match ($gateway) {
            'chip' => $this->verifyChipSignature($request),
            'stripe' => $this->verifyStripeSignature($request),
            default => false,
        };
    }

    private function resolveGateway(Request $request, WebhookConfig $config): ?string
    {
        $route = $request->route();

        if ($route instanceof Route) {
            $routeName = $route->getName();

            if (! is_string($routeName)) {
                return null;
            }

            foreach (config('checkout.routes.webhooks', []) as $gateway => $webhook) {
                if (is_array($webhook) && ($webhook['config'] ?? null) === $config->name && $routeName === $config->name) {
                    return is_string($gateway) ? $gateway : null;
                }
            }

            return null;
        }

        foreach (config('checkout.routes.webhooks', []) as $gateway => $webhook) {
            if (is_array($webhook) && ($webhook['config'] ?? null) === $config->name) {
                return is_string($gateway) ? $gateway : null;
            }
        }

        return null;
    }

    private function hasKnownPayloadShape(Request $request, string $gateway): bool
    {
        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            $payload = $request->all();
        }

        if ($gateway === 'chip') {
            $reference = $payload['reference'] ?? null;
            $eventType = $payload['event_type'] ?? null;
            $status = $payload['status'] ?? null;

            return is_string($reference)
                && CheckoutPaymentReference::sessionId($reference) !== null
                && ((is_string($eventType) && $eventType !== '') || (is_string($status) && $status !== ''));
        }

        if ($gateway === 'stripe') {
            return is_string($payload['type'] ?? null)
                && is_array(data_get($payload, 'data.object'));
        }

        return false;
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
        $secret = config('checkout.webhooks.stripe.secret');

        if (! is_string($signature) || $signature === '' || ! is_string($secret) || $secret === '') {
            return false;
        }

        try {
            Webhook::constructEvent($request->getContent(), $signature, $secret);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
