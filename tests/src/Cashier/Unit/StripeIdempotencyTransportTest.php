<?php

declare(strict_types=1);

use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\OwnerScopedBillableUser;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

uses(CashierTestCase::class);

/*
|--------------------------------------------------------------------------
| CHK-06: the frozen key reaches Stripe's transport, not just metadata
|--------------------------------------------------------------------------
|
| The real Stripe SDK turns the `idempotency_key` request option into the
| `Idempotency-Key` HTTP header. These regressions capture the SDK's
| outbound request (installed SDK seam, no hand-rolled fake of the gateway)
| and assert the header, the absence of an invented `idempotency_key`
| parameter, and the collapse of repeated redrives onto one provider
| payment for the same key.
|
*/

final class Chk06StripeTransportClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, headers: list<string>, params: array<string, mixed>}> */
    public array $calls = [];

    /** @var array<string, array<string, mixed>> */
    private array $intentsByKey = [];

    public int $created = 0;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->calls[] = [
            'method' => $method,
            'url' => $absUrl,
            'headers' => $headers,
            'params' => $params,
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
            'id' => 'pi_chk06_' . $this->created,
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

function chk06StripeBillable(string $stripeId = 'cus_chk06'): OwnerScopedBillableUser
{
    $billable = new OwnerScopedBillableUser;
    $billable->forceFill([
        'id' => 'billable-' . bin2hex(random_bytes(8)),
        'stripe_id' => $stripeId,
    ]);

    return $billable;
}

it('sends the frozen key as the Idempotency-Key header for an explicit payment method charge', function (): void {
    config()->set('cashier.secret', 'sk_test_chk06');
    config()->set('cashier.currency', 'MYR');

    $http = new Chk06StripeTransportClient;
    $http->useFake();

    try {
        $payment = (new StripeGateway([]))->charge(
            chk06StripeBillable(),
            2500,
            'pm_card_visa',
            ['idempotency_key' => 'chk06-session:attempt:1'],
        );

        expect($http->calls)->toHaveCount(1);

        $call = $http->calls[0];

        expect($payment->id())->toBe('pi_chk06_1')
            ->and($call['url'])->toContain('/v1/payment_intents')
            ->and($http->header($call['headers'], 'Idempotency-Key'))->toBe('chk06-session:attempt:1')
            ->and($call['params']['amount'])->toBe(2500)
            ->and($call['params']['currency'])->toBe('MYR')
            ->and($call['params']['payment_method'])->toBe('pm_card_visa')
            ->and($call['params'])->not->toHaveKey('idempotency_key');
    } finally {
        $http->restore();
    }

    expect(ApiRequestor::httpClient())->toBeInstanceOf(CurlClient::class);
});

it('sends the header on the automatic-payment-methods branch without a payment method', function (): void {
    config()->set('cashier.secret', 'sk_test_chk06');
    config()->set('cashier.currency', 'MYR');

    $http = new Chk06StripeTransportClient;
    $http->useFake();

    try {
        $payment = (new StripeGateway([]))->charge(
            chk06StripeBillable(),
            2500,
            null,
            ['idempotency_key' => 'chk06-session:attempt:1'],
        );

        $call = $http->calls[0];

        expect($payment->id())->toBe('pi_chk06_1')
            ->and($http->header($call['headers'], 'Idempotency-Key'))->toBe('chk06-session:attempt:1')
            ->and($call['params']['automatic_payment_methods'])->toHaveKey('enabled')
            ->and($call['params'])->not->toHaveKey('payment_method')
            ->and($call['params'])->not->toHaveKey('idempotency_key');
    } finally {
        $http->restore();
    }
});

it('omits the header and any idempotency_key parameter when no key is frozen', function (): void {
    config()->set('cashier.secret', 'sk_test_chk06');
    config()->set('cashier.currency', 'MYR');

    $http = new Chk06StripeTransportClient;
    $http->useFake();

    try {
        (new StripeGateway([]))->charge(chk06StripeBillable(), 2500, 'pm_card_visa');

        $call = $http->calls[0];

        expect($http->header($call['headers'], 'Idempotency-Key'))->toBeNull()
            ->and($call['params'])->not->toHaveKey('idempotency_key');
    } finally {
        $http->restore();
    }
});

it('collapses repeated redrives of one attempt onto one provider payment', function (): void {
    config()->set('cashier.secret', 'sk_test_chk06');
    config()->set('cashier.currency', 'MYR');

    $http = new Chk06StripeTransportClient;
    $http->useFake();

    try {
        $gateway = new StripeGateway([]);
        $billable = chk06StripeBillable();

        $first = $gateway->charge($billable, 2500, 'pm_card_visa', ['idempotency_key' => 'chk06-session:attempt:1']);
        $second = $gateway->charge($billable, 2500, 'pm_card_visa', ['idempotency_key' => 'chk06-session:attempt:1']);

        expect($second->id())->toBe($first->id())
            ->and($http->created)->toBe(1)
            ->and($http->calls)->toHaveCount(2)
            ->and($http->header($http->calls[0]['headers'], 'Idempotency-Key'))->toBe('chk06-session:attempt:1')
            ->and($http->header($http->calls[1]['headers'], 'Idempotency-Key'))->toBe('chk06-session:attempt:1');
    } finally {
        $http->restore();
    }
});

it('uses a distinct key and a distinct provider payment for a separate attempt', function (): void {
    config()->set('cashier.secret', 'sk_test_chk06');
    config()->set('cashier.currency', 'MYR');

    $http = new Chk06StripeTransportClient;
    $http->useFake();

    try {
        $gateway = new StripeGateway([]);
        $billable = chk06StripeBillable();

        $first = $gateway->charge($billable, 2500, 'pm_card_visa', ['idempotency_key' => 'chk06-session:attempt:1']);
        $second = $gateway->charge($billable, 2500, 'pm_card_visa', ['idempotency_key' => 'chk06-session:attempt:2']);

        expect($second->id())->not->toBe($first->id())
            ->and($http->created)->toBe(2)
            ->and($http->header($http->calls[0]['headers'], 'Idempotency-Key'))->toBe('chk06-session:attempt:1')
            ->and($http->header($http->calls[1]['headers'], 'Idempotency-Key'))->toBe('chk06-session:attempt:2');
    } finally {
        $http->restore();
    }
});
