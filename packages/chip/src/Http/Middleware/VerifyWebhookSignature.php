<?php

declare(strict_types=1);

namespace AIArmada\Chip\Http\Middleware;

use AIArmada\Chip\Exceptions\WebhookVerificationException;
use AIArmada\Chip\Services\WebhookService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $loggingChannel = config('chip.logging.channel', 'stack');

        try {
            $signature = $request->header('X-Signature');

            if (! $signature) {
                Log::channel($loggingChannel)->warning('CHIP webhook received without signature header', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);

                return response()->json([
                    'error' => 'Missing signature header',
                ], 400);
            }

            $verified = $this->webhookService->verifySignature($request);

            if (! $verified) {
                Log::channel($loggingChannel)->warning('CHIP webhook signature verification failed', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);

                return response()->json([
                    'error' => 'Invalid signature',
                ], 401);
            }

            if (config('chip.webhooks.log_payloads', false)) {
                Log::channel($loggingChannel)->info('CHIP webhook received', [
                    'event_type' => $request->input('event_type'),
                    'id' => $request->input('id'),
                    'payload' => $this->maskSensitiveData($request->all()),
                ]);
            }

            return $next($request);
        } catch (WebhookVerificationException $e) {
            Log::channel($loggingChannel)->error('CHIP webhook verification exception', [
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Mask sensitive data in webhook payload for logging.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function maskSensitiveData(array $data): array
    {
        if (! config('chip.logging.mask_sensitive_data', true)) {
            return $data;
        }

        $sensitiveKeys = [
            'email',
            'phone',
            'personal_code',
            'card_number',
            'account_number',
            'bank_account',
            'ip_address',
        ];

        return $this->maskRecursive($data, $sensitiveKeys);
    }

    /**
     * Recursively mask sensitive data.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $sensitiveKeys
     * @return array<string, mixed>
     */
    protected function maskRecursive(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskRecursive($value, $sensitiveKeys);
            } elseif (is_string($value) && in_array(mb_strtolower((string) $key), $sensitiveKeys, true)) {
                $data[$key] = str_repeat('*', min(mb_strlen($value), 8));
            }
        }

        return $data;
    }
}
