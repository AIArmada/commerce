<?php

declare(strict_types=1);

use AIArmada\Cashier\Drivers\ChipGateway;
use AIArmada\Cashier\Drivers\StripeGateway;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\OwnerScopedBillableUser;
use AIArmada\Commerce\Tests\Cashier\Fixtures\Tenant;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

uses(CashierTestCase::class);

beforeEach(function (): void {
    config()->set('cashier.models.billable', OwnerScopedBillableUser::class);
    config()->set('commerce-support.owner.enabled', true);
    config()->set('cashier.tests.owner.enabled', true);
});

it('blocks a foreign Stripe refund before sending the provider mutation', function (): void {
    config()->set('cashier.secret', 'sk_test_refund_scope');

    $owner = Tenant::query()->create(['name' => 'Payment owner']);
    $other = Tenant::query()->create(['name' => 'Other owner']);

    OwnerContext::withOwner($owner, fn () => OwnerScopedBillableUser::query()->create([
        'name' => 'Payment customer',
        'email' => 'payment-owner@example.com',
        'stripe_id' => 'cus_refund_scope',
    ]));

    $http = new class implements ClientInterface
    {
        public array $methods = [];

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $this->methods[] = $method;

            return [(string) json_encode([
                'id' => 'pi_refund_scope',
                'object' => 'payment_intent',
                'customer' => 'cus_refund_scope',
                'amount' => 1000,
                'currency' => 'usd',
                'status' => 'succeeded',
            ]), 200, []];
        }
    };

    ApiRequestor::setHttpClient($http);

    try {
        $gateway = new StripeGateway;

        expect(fn () => OwnerContext::withOwner($other, fn () => $gateway->refund('pi_refund_scope')))
            ->toThrow(AuthorizationException::class);

        expect($http->methods)->toHaveCount(1)
            ->and(mb_strtolower((string) $http->methods[0]))->toBe('get');
    } finally {
        ApiRequestor::setHttpClient(CurlClient::instance());
    }
});

it('blocks a CHIP refund without a matching cashier billable even when native purchase owner mode is disabled', function (): void {
    config()->set('chip.owner.enabled', false);

    $owner = Tenant::query()->create(['name' => 'Current owner']);

    $purchase = PurchaseData::from([
        'id' => 'purchase-refund-foreign',
        'created_on' => 1704067200,
        'updated_on' => 1704070800,
        'client' => ['email' => 'other-tenant@example.com'],
        'client_id' => 'chip_cus_refund_foreign',
        'purchase' => [
            'currency' => 'MYR',
            'total' => 7500,
            'products' => [[
                'name' => 'Other tenant item',
                'price' => 7500,
                'quantity' => 1,
                'discount' => 0,
                'tax_percent' => 0.0,
            ]],
        ],
        'brand_id' => 'brand_123',
        'status' => 'paid',
    ]);

    $service = Mockery::mock(ChipCollectService::class);
    $service->shouldReceive('getPurchase')->once()->with('purchase-refund-foreign')->andReturn($purchase);
    $service->shouldNotReceive('refundPurchase');
    app()->instance(ChipCollectService::class, $service);

    expect(fn () => OwnerContext::withOwner($owner, fn () => (new ChipGateway)->refund('purchase-refund-foreign')))
        ->toThrow(AuthorizationException::class);
});
