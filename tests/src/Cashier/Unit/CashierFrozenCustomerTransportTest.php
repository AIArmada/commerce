<?php

declare(strict_types=1);

use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\Payment\CashierProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\OwnerScopedBillableUser;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Str;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

uses(CashierTestCase::class);

/*
|--------------------------------------------------------------------------
| CHK-10: provider customer identity is frozen through the real SDK
|--------------------------------------------------------------------------
|
| A frozen checkout request carries the Stripe customer observed at claim
| time. The installed Stripe SDK is the transport seam: these tests capture
| the outbound PaymentIntent parameters and prove that mutating the local
| billable (or leaving its stripe_id null) cannot change a replay.
|
*/

final class Chk10StripeTransportClient implements ClientInterface
{
    /** @var list<array{params: array<string, mixed>, headers: list<string>}> */
    public array $calls = [];

    /** @var array<string, array<string, mixed>> */
    private array $intentsByKey = [];

    public int $created = 0;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->calls[] = [
            'params' => $params,
            'headers' => $headers,
        ];

        if (! str_contains((string) $absUrl, '/v1/payment_intents')) {
            return [(string) json_encode(['error' => ['message' => 'Unexpected endpoint']]), 400, []];
        }

        $key = $this->header($headers, 'Idempotency-Key');

        if ($key !== null && isset($this->intentsByKey[$key])) {
            return [(string) json_encode($this->intentsByKey[$key]), 200, []];
        }

        $this->created++;

        $intent = [
            'id' => 'pi_chk10_' . $this->created,
            'object' => 'payment_intent',
            'amount' => $params['amount'] ?? 0,
            'currency' => mb_strtolower((string) ($params['currency'] ?? 'usd')),
            'status' => 'succeeded',
        ];

        if ($key !== null) {
            $this->intentsByKey[$key] = $intent;
        }

        return [(string) json_encode($intent), 200, []];
    }

    /**
     * @param  list<string>  $headers
     */
    public function header(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (str_starts_with($header, $name . ':')) {
                return mb_trim(mb_substr($header, mb_strlen($name) + 1));
            }
        }

        return null;
    }

    public function useFake(): void
    {
        ApiRequestor::setHttpClient($this);
    }

    public function restore(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());
    }
}

function chk10Session(): CheckoutSession
{
    $session = new CheckoutSession;
    $session->setAttribute('id', (string) Str::uuid());
    $session->setAttribute('gateway', 'cashier');

    return $session;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function chk10Request(OwnerScopedBillableUser $billable, string $idempotencyKey, array $overrides = []): PaymentRequest
{
    $base = [
        'amount' => 2500,
        'currency' => 'USD',
        'gateway' => 'cashier',
        'description' => 'Frozen Stripe order',
        'paymentMethod' => 'pm_card_visa',
        'provider' => 'stripe',
        'idempotencyKey' => $idempotencyKey,
        'billableType' => OwnerScopedBillableUser::class,
        'billableId' => (string) $billable->getKey(),
        'clientFrozen' => true,
        'providerCustomerFrozen' => true,
    ];

    return new PaymentRequest(...array_merge($base, $overrides));
}

function chk10Billable(string $stripeId): OwnerScopedBillableUser
{
    return OwnerContext::withOwner(null, static fn (): OwnerScopedBillableUser => OwnerScopedBillableUser::query()->create([
        'name' => 'Frozen Stripe Customer',
        'email' => 'stripe-' . Str::random(8) . '@example.com',
        'stripe_id' => $stripeId,
    ]));
}

it('sends the frozen Stripe customer after the billable stripe_id mutates', function (): void {
    config()->set('cashier.secret', 'sk_test_chk10');
    config()->set('cashier.currency', 'USD');

    $http = new Chk10StripeTransportClient;
    $http->useFake();

    try {
        $billable = chk10Billable('cus_frozen_chk10');
        $session = chk10Session();
        $request = chk10Request($billable, 'chk10-session:attempt:1', [
            'providerCustomerId' => 'cus_frozen_chk10',
        ]);

        // The same persisted customer changes its provider identity after the
        // request was frozen. The replay must still target the frozen customer.
        OwnerContext::withOwner(null, static function () use ($billable): void {
            $billable->forceFill([
                'stripe_id' => 'cus_mutated_chk10',
                'name' => 'Mutated Stripe Name',
                'email' => 'mutated-stripe@example.com',
                'phone' => '+15550009999',
            ])->save();
        });

        $first = app(CashierProcessor::class)->createPayment($session, $request);
        $second = app(CashierProcessor::class)->createPayment($session, $request);

        expect($first->status)->toBe(PaymentStatus::Completed)
            ->and($second->paymentId)->toBe($first->paymentId)
            ->and($http->calls)->toHaveCount(2)
            ->and($http->created)->toBe(1)
            ->and($http->calls[0]['params']['customer'])->toBe('cus_frozen_chk10')
            ->and($http->calls[1]['params']['customer'])->toBe('cus_frozen_chk10')
            ->and($http->calls[0]['params']['customer'])->not->toBe('cus_mutated_chk10')
            ->and($http->header($http->calls[0]['headers'], 'Idempotency-Key'))->toBe('chk10-session:attempt:1')
            ->and($http->header($http->calls[1]['headers'], 'Idempotency-Key'))->toBe('chk10-session:attempt:1');
    } finally {
        $http->restore();
    }
});

it('keeps an explicitly frozen null customer instead of falling back to stripe_id', function (): void {
    config()->set('cashier.secret', 'sk_test_chk10');
    config()->set('cashier.currency', 'USD');

    $http = new Chk10StripeTransportClient;
    $http->useFake();

    try {
        $billable = chk10Billable('cus_live_must_not_leak');
        $session = chk10Session();
        $request = chk10Request($billable, 'chk10-null:attempt:1', [
            'providerCustomerId' => null,
        ]);

        $result = app(CashierProcessor::class)->createPayment($session, $request);

        expect($result->status)->toBe(PaymentStatus::Completed)
            ->and($http->calls)->toHaveCount(1)
            ->and($http->calls[0]['params'])->not->toHaveKey('customer')
            ->and($http->calls[0]['params'])->not->toHaveKey('provider_customer_id')
            ->and($http->calls[0]['params'])->not->toHaveKey('provider_customer_frozen');
    } finally {
        $http->restore();
    }
});

it('uses the new frozen customer for a replacement generation', function (): void {
    config()->set('cashier.secret', 'sk_test_chk10');
    config()->set('cashier.currency', 'USD');

    $http = new Chk10StripeTransportClient;
    $http->useFake();

    try {
        $billable = chk10Billable('cus_first_chk10');
        $session = chk10Session();

        $first = chk10Request($billable, 'chk10-replace:attempt:1', [
            'providerCustomerId' => 'cus_first_chk10',
        ]);

        OwnerContext::withOwner(null, static function () use ($billable): void {
            $billable->forceFill(['stripe_id' => 'cus_second_chk10'])->save();
        });

        $second = chk10Request($billable, 'chk10-replace:attempt:2', [
            'providerCustomerId' => 'cus_second_chk10',
        ]);

        app(CashierProcessor::class)->createPayment($session, $first);
        app(CashierProcessor::class)->createPayment($session, $second);

        expect($http->calls)->toHaveCount(2)
            ->and($http->calls[0]['params']['customer'])->toBe('cus_first_chk10')
            ->and($http->calls[1]['params']['customer'])->toBe('cus_second_chk10')
            ->and($http->created)->toBe(2);
    } finally {
        $http->restore();
    }
});

it('fails before transport when the frozen Stripe billable no longer exists', function (): void {
    config()->set('cashier.secret', 'sk_test_chk10');
    config()->set('cashier.currency', 'USD');

    $http = new Chk10StripeTransportClient;
    $http->useFake();

    try {
        $billable = chk10Billable('cus_deleted_chk10');
        $session = chk10Session();
        $request = chk10Request($billable, 'chk10-deleted:attempt:1', [
            'providerCustomerId' => 'cus_deleted_chk10',
        ]);

        OwnerContext::withOwner(null, static fn (): mixed => $billable->delete());

        $result = app(CashierProcessor::class)->createPayment($session, $request);

        expect($result->status)->toBe(PaymentStatus::Failed)
            ->and($result->message)->toContain('billable')
            ->and($http->calls)->toBeEmpty();
    } finally {
        $http->restore();
    }
});
