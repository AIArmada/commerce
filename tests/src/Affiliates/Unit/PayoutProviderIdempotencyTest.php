<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Services\Payouts\PayPalProcessor;
use AIArmada\Affiliates\Services\Payouts\StripeConnectProcessor;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ProcessingPayout;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function createProviderPayout(PayoutMethodType $type, array $details, int $amountMinor = 10_000): AffiliatePayout
{
    $affiliate = Affiliate::query()->create([
        'code' => 'PROVIDER-' . uniqid(),
        'name' => 'Provider Test Affiliate',
        'contact_email' => uniqid() . '@example.test',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliatePayoutMethod::query()->create([
        'affiliate_id' => $affiliate->id,
        'type' => $type,
        'details' => $details,
        'is_default' => true,
    ]);

    $operation = AffiliatePayoutOperation::query()->create([
        'affiliate_id' => $affiliate->id,
        'operation_key' => 'provider:' . uniqid(),
        'status' => 'submitting',
        'amount_minor' => $amountMinor,
        'currency' => 'USD',
        'claimed_at' => now(),
    ]);

    $payout = AffiliatePayout::query()->create([
        'affiliate_payout_operation_id' => $operation->id,
        'reference' => 'PAY-' . uniqid(),
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->id,
        'total_minor' => $amountMinor,
        'currency' => 'USD',
        'status' => ProcessingPayout::class,
    ]);

    $operation->forceFill(['affiliate_payout_id' => $payout->id])->save();

    return $payout->fresh(['operation', 'payee']);
}

test('stripe uses the operation id as the provider idempotency key', function (): void {
    config()->set('affiliates.payouts.stripe.secret_key', 'sk_test_secret');
    $payout = createProviderPayout(PayoutMethodType::StripeConnect, ['stripe_account_id' => 'acct_123456']);

    Http::fake(['api.stripe.com/*' => Http::response(['id' => 'tr_123'], 200)]);

    $result = (new StripeConnectProcessor)->process($payout);

    expect($result->getStatus())->toBe('completed')
        ->and($result->externalReference)->toBe('tr_123');

    Http::assertSent(function (Request $request) use ($payout): bool {
        return $request->url() === 'https://api.stripe.com/v1/transfers'
            && $request->hasHeader('Idempotency-Key', (string) $payout->operation?->id);
    });
});

test('retryable stripe failures become unknown without exposing provider text', function (): void {
    config()->set('affiliates.payouts.stripe.secret_key', 'sk_test_secret');
    $payout = createProviderPayout(PayoutMethodType::StripeConnect, ['stripe_account_id' => 'acct_123456']);

    Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'secret upstream detail']], 500)]);

    $result = (new StripeConnectProcessor)->process($payout);

    expect($result->isUnknown())->toBeTrue()
        ->and($result->failureReason)->not->toContain('secret upstream detail');
});

test('paypal derives one stable sender batch identity from the operation', function (): void {
    config()->set('affiliates.payouts.paypal.client_id', 'client');
    config()->set('affiliates.payouts.paypal.client_secret', 'secret');
    $payout = createProviderPayout(PayoutMethodType::PayPal, ['email' => 'affiliate@example.test']);
    $expected = mb_substr(hash('sha256', (string) $payout->operation?->id), 0, 30);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        'api-m.sandbox.paypal.com/v1/payments/payouts' => Http::response(['batch_header' => ['payout_batch_id' => 'BATCH-1']], 201),
    ]);

    $result = (new PayPalProcessor)->process($payout);

    expect($result->isPending())->toBeTrue();
    Http::assertSent(function (Request $request) use ($expected): bool {
        return $request->url() === 'https://api-m.sandbox.paypal.com/v1/payments/payouts'
            && data_get($request->data(), 'sender_batch_header.sender_batch_id') === $expected
            && data_get($request->data(), 'items.0.sender_item_id') === $expected;
    });
});

test('stripe reversals are locally and remotely idempotent', function (): void {
    config()->set('affiliates.payouts.stripe.secret_key', 'sk_test_secret');
    $payout = createProviderPayout(PayoutMethodType::StripeConnect, ['stripe_account_id' => 'acct_123456']);
    $payout->external_reference = 'tr_123';
    $payout->save();

    Http::fake(['api.stripe.com/*' => Http::response(['id' => 'trr_123'], 200)]);
    $processor = new StripeConnectProcessor;

    expect($processor->cancel($payout->fresh(['operation'])))->toBeTrue()
        ->and($processor->cancel($payout->fresh(['operation'])))->toBeTrue();

    Http::assertSentCount(1);
});
