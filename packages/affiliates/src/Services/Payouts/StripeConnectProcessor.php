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

final class StripeConnectProcessor implements PayoutProcessorInterface
{
    private string $apiKey;

    private string $apiUrl = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->apiKey = (string) config('affiliates.payouts.stripe.secret_key', '');
    }

    public function process(AffiliatePayout $payout): PayoutResult
    {
        if ($this->apiKey === '') {
            return PayoutResult::failure('Stripe is not configured.', 'STRIPE_NOT_CONFIGURED');
        }

        $operation = $payout->operation;
        $affiliate = $payout->affiliate;

        if ($operation === null || $affiliate === null) {
            return PayoutResult::failure('The payout operation is invalid.', 'INVALID_PAYOUT_OPERATION');
        }

        $method = $affiliate->payoutMethods()->where('type', 'stripe_connect')->where('is_default', true)->first();
        $accountId = is_array($method?->details) ? ($method->details['stripe_account_id'] ?? null) : null;

        if (! is_string($accountId) || ! str_starts_with($accountId, 'acct_')) {
            return PayoutResult::failure('The Stripe payout destination is invalid.', 'INVALID_STRIPE_ACCOUNT');
        }

        $netAmount = $payout->total_minor - $this->getFees($payout->total_minor, $payout->currency);

        if ($netAmount <= 0) {
            return PayoutResult::failure('The payout amount does not cover provider fees.', 'NON_POSITIVE_NET_AMOUNT');
        }

        try {
            $response = $this->request()
                ->withHeaders(['Idempotency-Key' => $operation->id])
                ->asForm()
                ->post("{$this->apiUrl}/transfers", [
                    'amount' => $netAmount,
                    'currency' => mb_strtolower($payout->currency),
                    'destination' => $accountId,
                    'transfer_group' => $operation->id,
                    'metadata' => [
                        'payout_id' => $payout->id,
                        'operation_id' => $operation->id,
                    ],
                ]);

            if ($response->successful() && is_string($response->json('id'))) {
                return PayoutResult::success((string) $response->json('id'), ['provider' => 'stripe']);
            }

            return $this->classifyFailure($response);
        } catch (ConnectionException) {
            return PayoutResult::unknown('STRIPE_CONNECTION_OUTCOME_UNKNOWN');
        } catch (Throwable $throwable) {
            Log::error('Stripe payout submission failed unexpectedly.', [
                'payout_id' => $payout->id,
                'exception' => $throwable::class,
            ]);

            return PayoutResult::unknown('STRIPE_SUBMISSION_OUTCOME_UNKNOWN');
        }
    }

    public function getStatus(AffiliatePayout $payout): string
    {
        if ($this->apiKey === '') {
            return 'unknown';
        }

        try {
            if (is_string($payout->external_reference) && $payout->external_reference !== '') {
                $response = $this->request(attempts: 1)->get("{$this->apiUrl}/transfers/{$payout->external_reference}");

                return $response->successful() ? 'completed' : ($response->status() === 404 ? 'not_found' : 'unknown');
            }

            $operation = $payout->operation;

            if ($operation === null) {
                return 'unknown';
            }

            $response = $this->request(attempts: 1)->get("{$this->apiUrl}/transfers", [
                'transfer_group' => $operation->id,
                'limit' => 1,
            ]);

            if (! $response->successful()) {
                return 'unknown';
            }

            $reference = $response->json('data.0.id');

            if (is_string($reference) && $reference !== '') {
                $operation->forceFill(['provider_reference' => $reference])->save();
                $payout->external_reference = $reference;
                $payout->save();

                return 'completed';
            }

            return 'not_found';
        } catch (Throwable $throwable) {
            Log::warning('Stripe payout reconciliation request failed.', [
                'payout_id' => $payout->id,
                'exception' => $throwable::class,
            ]);

            return 'unknown';
        }
    }

    public function cancel(AffiliatePayout $payout): bool
    {
        $operation = $payout->operation;

        if ($operation === null || $this->apiKey === '' || ! is_string($payout->external_reference) || $payout->external_reference === '') {
            return false;
        }

        if ($operation->status === 'reversed') {
            return true;
        }

        try {
            $response = $this->request()
                ->withHeaders(['Idempotency-Key' => $operation->id . ':reversal'])
                ->asForm()
                ->post("{$this->apiUrl}/transfers/{$payout->external_reference}/reversals");

            if (! $response->successful()) {
                return false;
            }

            $operation->forceFill(['status' => 'reversed', 'completed_at' => now()])->save();

            return true;
        } catch (Throwable $throwable) {
            Log::warning('Stripe payout reversal outcome is unknown.', [
                'payout_id' => $payout->id,
                'exception' => $throwable::class,
            ]);

            return false;
        }
    }

    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface
    {
        return now()->addDays(2);
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return (int) ceil($amountMinor * 0.0025) + 25;
    }

    public function validateDetails(array $details): array
    {
        $accountId = $details['stripe_account_id'] ?? null;

        if (! is_string($accountId) || ! str_starts_with($accountId, 'acct_')) {
            return ['stripe_account_id' => 'A valid Stripe account ID is required'];
        }

        return [];
    }

    public function getIdentifier(): string
    {
        return 'stripe_connect';
    }

    private function request(?int $attempts = null): PendingRequest
    {
        $attempts ??= max(1, (int) config('affiliates.payouts.transport.attempts', 2));

        return Http::withBasicAuth($this->apiKey, '')
            ->connectTimeout(max(1, (int) config('affiliates.payouts.transport.connect_timeout_seconds', 3)))
            ->timeout(max(1, (int) config('affiliates.payouts.transport.timeout_seconds', 15)))
            ->retry($attempts, max(0, (int) config('affiliates.payouts.transport.retry_delay_milliseconds', 100)), throw: false);
    }

    private function classifyFailure(Response $response): PayoutResult
    {
        if (in_array($response->status(), [408, 409, 425, 429], true) || $response->serverError()) {
            return PayoutResult::unknown('STRIPE_HTTP_OUTCOME_UNKNOWN');
        }

        $providerCode = $response->json('error.code');
        $safeCode = is_string($providerCode) && preg_match('/^[A-Za-z0-9_.-]{1,48}$/', $providerCode) === 1
            ? 'STRIPE_' . mb_strtoupper($providerCode)
            : 'STRIPE_REQUEST_REJECTED';

        return PayoutResult::failure('Stripe rejected the payout request.', $safeCode);
    }
}
