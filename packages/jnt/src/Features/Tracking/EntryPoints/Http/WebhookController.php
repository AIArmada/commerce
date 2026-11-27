<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\EntryPoints\Http;

use AIArmada\Jnt\Exceptions\JntValidationException;
use AIArmada\Jnt\Features\Tracking\Support\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\WebhookClient\Exceptions\InvalidWebhookSignature;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookProcessor;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Controller for handling J&T Express webhook requests.
 *
 * This controller processes incoming webhook notifications from J&T Express
 * regarding tracking status updates.
 */
class WebhookController
{
    /**
     * Create a new webhook controller instance.
     */
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle incoming J&T Express webhook notification.
     *
     * This endpoint receives tracking status updates from J&T Express servers.
     *
     * @param  Request  $request  The incoming webhook request
     * @return JsonResponse The webhook response in J&T's expected format
     */
    public function handle(Request $request, WebhookConfig $config): JsonResponse
    {
        try {
            if (! (bool) config('jnt.webhooks.enabled', true)) {
                throw new NotFoundHttpException;
            }

            $content = $request->input('bizContent');
            $maxBytes = max(1, (int) config('jnt.webhooks.max_biz_content_bytes', 1048576));

            if (is_string($content) && mb_strlen($content) > $maxBytes) {
                throw JntValidationException::fieldTooLong('bizContent', $maxBytes, mb_strlen($content));
            }

            if ($config->signingSecret === '') {
                $config->signingSecret = (string) config('jnt.private_key', '');
            }

            $response = (new WebhookProcessor($request, $config))->process();

            if (config('jnt.webhooks.log_payloads', false)) {
                try {
                    $webhookData = $this->webhookService->parseWebhook($request);

                    Log::info('J&T webhook received', [
                        'billCode' => $webhookData->billCode,
                        'txlogisticId' => $webhookData->txlogisticId,
                        'detailsCount' => count($webhookData->details),
                        'latestStatus' => $webhookData->getLatestDetail()?->scanType ?? 'unknown',
                    ]);
                } catch (Throwable $e) {
                    Log::warning('J&T webhook logging skipped (invalid payload)', [
                        'exception' => $e::class,
                    ]);
                }
            }

            /** @var JsonResponse $response */
            return $response;
        } catch (ValidationException $e) {
            // Invalid request structure
            Log::warning('J&T webhook validation failed', [
                'errors' => $e->errors(),
            ]);

            $response = $this->webhookService->failureResponse('Invalid request structure');

            return response()->json($response, 422);
        } catch (JntValidationException $e) {
            // Invalid bizContent
            Log::warning('J&T webhook processing failed', [
                'field' => $e->field ?? 'unknown',
            ]);

            $response = $this->webhookService->failureResponse('Invalid payload');

            return response()->json($response, 422);
        } catch (InvalidWebhookSignature) {
            Log::warning('J&T webhook signature verification failed', [
                'ip' => $request->ip(),
                'digest_present' => $request->header('digest') !== null,
                'bizContent_present' => $request->filled('bizContent'),
            ]);

            $response = $this->webhookService->failureResponse('Invalid signature');

            return response()->json($response, 401);
        } catch (NotFoundHttpException) {
            return response()->json([
                'message' => 'Not Found',
            ], 404);
        } catch (Throwable $e) {
            // Unexpected error
            Log::error('J&T webhook processing error', [
                'exception' => $e::class,
            ]);

            $response = $this->webhookService->failureResponse('Internal server error');

            return response()->json($response, 500);
        }
    }
}
