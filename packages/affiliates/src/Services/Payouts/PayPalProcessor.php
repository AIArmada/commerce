<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services\Payouts;

use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Models\AffiliatePayout;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PayPalProcessor implements PayoutProcessorInterface
{
    private string $clientId;

    private string $clientSecret;

    private string $apiUrl;

    private ?string $accessToken = null;

    public function __construct()
    {
        $this->clientId = (string) config('affiliates.payouts.paypal.client_id', '');
        $this->clientSecret = (string) config('affiliates.payouts.paypal.client_secret', '');
        $this->apiUrl = config('affiliates.payouts.paypal.sandbox', true)
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    public function process(AffiliatePayout $payout): PayoutResult
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            return PayoutResult::failure('PayPal is not configured.', 'PAYPAL_NOT_CONFIGURED');
        }

        $operation = $payout->operation;
        $affiliate = $payout->affiliate;

        if ($operation === null || $affiliate === null) {
            return PayoutResult::failure('The payout operation is invalid.', 'INVALID_PAYOUT_OPERATION');
        }

        $method = $affiliate->payoutMethods()->where('type', 'paypal')->where('is_default', true)->first();
        $email = is_array($method?->details) ? ($method->details['email'] ?? null) : null;

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return PayoutResult::failure('The PayPal payout destination is invalid.', 'INVALID_PAYPAL_ACCOUNT');
        }

        $netAmount = $payout->total_minor - $this->getFees($payout->total_minor, $payout->currency);

        if ($netAmount <= 0) {
            return PayoutResult::failure('The payout amount does not cover provider fees.', 'NON_POSITIVE_NET_AMOUNT');
        }

        $token = $this->getAccessToken();

        if ($token === null) {
            return PayoutResult::unknown('PAYPAL_AUTH_OUTCOME_UNKNOWN');
        }

        $providerOperationId = mb_substr(hash('sha256', $operation->id), 0, 30);

        try {
            $response = $this->request()->withToken($token)->post("{$this->apiUrl}/v1/payments/payouts", [
                'sender_batch_header' => [
                    'sender_batch_id' => $providerOperationId,
                    'email_subject' => 'You have a payout',
                ],
                'items' => [[
                    'recipient_type' => 'EMAIL',
                    'amount' => [
                        'value' => number_format($netAmount / 100, 2, '.', ''),
                        'currency' => mb_strtoupper($payout->currency),
                    ],
                    'sender_item_id' => $providerOperationId,
                    'receiver' => $email,
                ]],
            ]);

            $batchId = $response->json('batch_header.payout_batch_id');

            if ($response->successful() && is_string($batchId) && $batchId !== '') {
                return PayoutResult::pending($batchId, ['provider' => 'paypal']);
            }

            return $this->classifyFailure($response);
        } catch (ConnectionException) {
            return PayoutResult::unknown('PAYPAL_CONNECTION_OUTCOME_UNKNOWN');
        } catch (Throwable $throwable) {
            Log::error('PayPal payout submission failed unexpectedly.', [
                'payout_id' => $payout->id,
                'exception' => $throwable::class,
            ]);

            return PayoutResult::unknown('PAYPAL_SUBMISSION_OUTCOME_UNKNOWN');
        }
    }

    public function getStatus(AffiliatePayout $payout): string
    {
        if (! is_string($payout->external_reference) || $payout->external_reference === '') {
            return 'unknown';
        }

        $token = $this->getAccessToken();

        if ($token === null) {
            return 'unknown';
        }

        try {
            $response = $this->request(attempts: 1)->withToken($token)
                ->get("{$this->apiUrl}/v1/payments/payouts/{$payout->external_reference}");

            if ($response->status() === 404) {
                return 'not_found';
            }

            if (! $response->successful()) {
                return 'unknown';
            }

            return match ((string) $response->json('batch_header.batch_status')) {
                'SUCCESS' => 'completed',
                'PENDING', 'PROCESSING', 'NEW' => 'processing',
                'DENIED', 'CANCELED' => 'failed',
                default => 'unknown',
            };
        } catch (Throwable $throwable) {
            Log::warning('PayPal payout reconciliation request failed.', [
                'payout_id' => $payout->id,
                'exception' => $throwable::class,
            ]);

            return 'unknown';
        }
    }

    public function cancel(AffiliatePayout $payout): bool
    {
        return false;
    }

    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface
    {
        return now()->addDays(1);
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return min((int) ceil($amountMinor * 0.02), 100);
    }

    public function validateDetails(array $details): array
    {
        $email = $details['email'] ?? null;

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['email' => 'A valid PayPal email is required'];
        }

        return [];
    }

    public function getIdentifier(): string
    {
        return 'paypal';
    }

    private function getAccessToken(): ?string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        try {
            $response = $this->request()->withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->apiUrl}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

            $token = $response->successful() ? $response->json('access_token') : null;

            if (is_string($token) && $token !== '') {
                return $this->accessToken = $token;
            }
        } catch (Throwable $throwable) {
            Log::warning('PayPal OAuth request failed.', ['exception' => $throwable::class]);
        }

        return null;
    }

    private function request(?int $attempts = null): PendingRequest
    {
        $attempts ??= max(1, (int) config('affiliates.payouts.transport.attempts', 2));

        return Http::connectTimeout(max(1, (int) config('affiliates.payouts.transport.connect_timeout_seconds', 3)))
            ->timeout(max(1, (int) config('affiliates.payouts.transport.timeout_seconds', 15)))
            ->retry($attempts, max(0, (int) config('affiliates.payouts.transport.retry_delay_milliseconds', 100)), throw: false);
    }

    private function classifyFailure(Response $response): PayoutResult
    {
        $providerCode = $response->json('name');

        if (in_array($response->status(), [408, 409, 425, 429], true) || $response->serverError() || $providerCode === 'DUPLICATE_REQUEST_ID') {
            return PayoutResult::unknown('PAYPAL_HTTP_OUTCOME_UNKNOWN');
        }

        $safeCode = is_string($providerCode) && preg_match('/^[A-Za-z0-9_.-]{1,48}$/', $providerCode) === 1
            ? 'PAYPAL_' . mb_strtoupper($providerCode)
            : 'PAYPAL_REQUEST_REJECTED';

        return PayoutResult::failure('PayPal rejected the payout request.', $safeCode);
    }
}
