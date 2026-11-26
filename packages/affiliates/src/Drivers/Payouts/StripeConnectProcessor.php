<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Payouts;

use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Data\PayoutRequest;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Data\PayoutStatusResult;
use Carbon\CarbonImmutable;
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

    public function process(PayoutRequest $payout): PayoutResult
    {
        if ($this->apiKey === '') {
            return PayoutResult::failure('Stripe is not configured.', 'STRIPE_NOT_CONFIGURED');
        }

        $operationId = $payout->operationId;

        if ($operationId === null || ! $payout->hasAffiliate) {
            return PayoutResult::failure('The payout operation is invalid.', 'INVALID_PAYOUT_OPERATION');
        }

        $accountId = $payout->methodDetails['stripe_account_id'] ?? null;

        if (! is_string($accountId) || ! str_starts_with($accountId, 'acct_')) {
            return PayoutResult::failure('The Stripe payout destination is invalid.', 'INVALID_STRIPE_ACCOUNT');
        }

        $netAmount = $payout->totalMinor - $this->getFees($payout->totalMinor, $payout->currency);

        if ($netAmount <= 0) {
            return PayoutResult::failure('The payout amount does not cover provider fees.', 'NON_POSITIVE_NET_AMOUNT');
        }

        try {
            $response = $this->request()
                ->withHeaders(['Idempotency-Key' => $operationId])
                ->asForm()
                ->post("{$this->apiUrl}/transfers", [
                    'amount' => $netAmount,
                    'currency' => mb_strtolower($payout->currency),
                    'destination' => $accountId,
                    'transfer_group' => $operationId,
                    'metadata' => [
                        'payout_id' => $payout->id,
                        'operation_id' => $operationId,
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

    public function getStatus(PayoutRequest $payout): PayoutStatusResult
    {
        if ($this->apiKey === '') {
            return new PayoutStatusResult('unknown');
        }

        try {
            if (is_string($payout->externalReference) && $payout->externalReference !== '') {
                $response = $this->request(attempts: 1)->get("{$this->apiUrl}/transfers/{$payout->externalReference}");

                return new PayoutStatusResult($response->successful() ? 'completed' : ($response->status() === 404 ? 'not_found' : 'unknown'), $payout->externalReference);
            }

            $operationId = $payout->operationId;

            if ($operationId === null) {
                return new PayoutStatusResult('unknown');
            }

            $response = $this->request(attempts: 1)->get("{$this->apiUrl}/transfers", [
                'transfer_group' => $operationId,
                'limit' => 1,
            ]);

            if (! $response->successful()) {
                return new PayoutStatusResult('unknown');
            }

            $reference = $response->json('data.0.id');

            if (is_string($reference) && $reference !== '') {
                return new PayoutStatusResult('completed', $reference);
            }

            return new PayoutStatusResult('not_found');
        } catch (Throwable $throwable) {
            Log::warning('Stripe payout reconciliation request failed.', [
                'payout_id' => $payout->id,
                'exception' => $throwable::class,
            ]);

            return new PayoutStatusResult('unknown');
        }
    }

    public function cancel(PayoutRequest $payout): bool
    {
        $operationId = $payout->operationId;

        if ($operationId === null || $this->apiKey === '' || ! is_string($payout->externalReference) || $payout->externalReference === '') {
            return false;
        }

        if ($payout->operationStatus === 'reversed') {
            return true;
        }

        try {
            $response = $this->request()
                ->withHeaders(['Idempotency-Key' => $operationId . ':reversal'])
                ->asForm()
                ->post("{$this->apiUrl}/transfers/{$payout->externalReference}/reversals");

            if (! $response->successful()) {
                return false;
            }

            return true;
        } catch (Throwable $throwable) {
            Log::warning('Stripe payout reversal outcome is unknown.', [
                'payout_id' => $payout->id,
                'exception' => $throwable::class,
            ]);

            return false;
        }
    }

    public function getEstimatedArrival(PayoutRequest $payout): ?DateTimeInterface
    {
        return CarbonImmutable::now()->addDays(2);
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        $percentageFee = intdiv($amountMinor, 10_000) * 25;
        $remainder = $amountMinor % 10_000;

        if ($remainder > 0) {
            $percentageFee += intdiv(($remainder * 25) + 9_999, 10_000);
        }

        return $percentageFee + 25;
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
