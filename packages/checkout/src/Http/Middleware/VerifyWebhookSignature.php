<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Http\Middleware;

use AIArmada\Checkout\Exceptions\WebhookVerificationException;
use AIArmada\Chip\Services\WebhookService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify webhook signatures for checkout payment webhooks.
 *
 * Delegates signature verification to the appropriate gateway's validator:
 * - CHIP: Uses AIArmada\Chip\Services\WebhookService
 * - Stripe: Uses stripe-signature header + stripe webhook secret
 *
 * This middleware ensures no duplicate signature verification logic and
 * leverages the battle-tested validators from each payment package.
 */
final class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $loggingChannel = config('checkout.webhooks.log_channel') ?? config('logging.default');

        // Detect which gateway sent this webhook
        $gateway = $this->detectGateway($request);

        if ($gateway === null) {
            Log::channel($loggingChannel)->warning('Checkout webhook received from unknown gateway', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'headers' => $this->getSafeHeaders($request),
            ]);

            return response()->json([
                'error' => 'Unable to identify webhook source',
            ], 400);
        }

        try {
            $verified = match ($gateway) {
                'chip' => $this->verifyChipSignature($request, $loggingChannel),
                'stripe' => $this->verifyStripeSignature($request, $loggingChannel),
                default => false,
            };

            if (! $verified) {
                Log::channel($loggingChannel)->warning('Checkout webhook signature verification failed', [
                    'gateway' => $gateway,
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);

                return response()->json([
                    'error' => 'Invalid signature',
                ], 401);
            }

            if (config('checkout.webhooks.log_payloads', false)) {
                Log::channel($loggingChannel)->info('Checkout webhook received', [
                    'gateway' => $gateway,
                    'event_type' => $request->input('event_type') ?? $request->input('type'),
                    'id' => $request->input('id') ?? $request->input('data.object.id'),
                ]);
            }

            return $next($request);
        } catch (WebhookVerificationException $e) {
            Log::channel($loggingChannel)->error('Checkout webhook verification exception', [
                'gateway' => $gateway,
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Detect which payment gateway sent this webhook.
     */
    private function detectGateway(Request $request): ?string
    {
        // CHIP signature header
        if ($request->hasHeader('X-Signature')) {
            return 'chip';
        }

        // Stripe signature header
        if ($request->hasHeader('Stripe-Signature')) {
            return 'stripe';
        }

        // CHIP-specific payload structure
        if ($request->has('reference') && $request->has('status')) {
            return 'chip';
        }

        // Stripe-specific payload structure
        if ($request->input('data.object') !== null && $request->has('type')) {
            return 'stripe';
        }

        return null;
    }

    /**
     * Verify CHIP webhook signature using the CHIP package's WebhookService.
     */
    private function verifyChipSignature(Request $request, string $loggingChannel): bool
    {
        // Delegate to CHIP package's verification if available
        if (! class_exists(WebhookService::class)) {
            Log::channel($loggingChannel)->error('CHIP webhook received but chip package is not installed');

            throw new WebhookVerificationException('CHIP package not installed');
        }

        // Check if CHIP verification is enabled
        if (! config('chip.webhooks.verify_signature', true)) {
            // Only allow skipping in non-production
            if (app()->environment('production')) {
                throw new WebhookVerificationException('Signature verification cannot be disabled in production');
            }

            Log::channel($loggingChannel)->warning('CHIP webhook signature verification is disabled');

            return true;
        }

        $signature = $request->header('X-Signature');

        if (! $signature) {
            throw new WebhookVerificationException('Missing X-Signature header');
        }

        // Use CHIP's WebhookService for verification
        /** @var WebhookService $webhookService */
        $webhookService = app(WebhookService::class);

        return $webhookService->verifySignature($request);
    }

    /**
     * Verify Stripe webhook signature.
     */
    private function verifyStripeSignature(Request $request, string $loggingChannel): bool
    {
        // Check if Cashier/Stripe is available
        if (! class_exists(Webhook::class)) {
            Log::channel($loggingChannel)->error('Stripe webhook received but stripe/stripe-php is not installed');

            throw new WebhookVerificationException('Stripe package not installed');
        }

        $signature = $request->header('Stripe-Signature');

        if (! $signature) {
            throw new WebhookVerificationException('Missing Stripe-Signature header');
        }

        $webhookSecret = config('cashier.webhook.secret');

        if (! $webhookSecret) {
            throw new WebhookVerificationException('Stripe webhook secret not configured');
        }

        try {
            Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $webhookSecret
            );

            return true;
        } catch (SignatureVerificationException) {
            return false;
        }
    }

    /**
     * Get safe headers for logging (exclude sensitive values).
     *
     * @return array<string, mixed>
     */
    private function getSafeHeaders(Request $request): array
    {
        $headers = [];
        $safeHeaders = ['content-type', 'user-agent', 'host', 'x-signature', 'stripe-signature'];

        foreach ($safeHeaders as $header) {
            if ($request->hasHeader($header)) {
                $headers[$header] = $header === 'x-signature' || $header === 'stripe-signature'
                    ? '[REDACTED]'
                    : $request->header($header);
            }
        }

        return $headers;
    }
}
