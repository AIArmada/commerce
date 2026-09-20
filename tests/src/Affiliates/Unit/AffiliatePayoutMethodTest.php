<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\States\Active;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// AffiliatePayoutMethod Tests
test('AffiliatePayoutMethod can be created with required fields', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAY001',
        'name' => 'Payout Method Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $method = AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->id,
        'type' => PayoutMethodType::PayPal,
        'details' => ['email' => 'affiliate@example.com'],
        'verified_at' => null,
        'is_default' => true,
    ]);

    expect($method)->toBeInstanceOf(AffiliatePayoutMethod::class);
    expect($method->type)->toBe(PayoutMethodType::PayPal);
});

test('AffiliatePayoutMethod has affiliate relationship', function (): void {
    $method = new AffiliatePayoutMethod;

    expect($method->affiliate())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliatePayoutMethod verify sets verified_at', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAY002',
        'name' => 'Verify Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $method = AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->id,
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '1234567890'],
        'verified_at' => null,
        'is_default' => false,
    ]);

    $method->verify();

    expect($method->fresh()->isVerified())->toBeTrue();
    expect($method->fresh()->verified_at)->not->toBeNull();
});

test('AffiliatePayoutMethod setAsDefault updates default flag', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAY003',
        'name' => 'Default Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $method1 = AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->id,
        'type' => PayoutMethodType::PayPal,
        'details' => ['email' => 'old@example.com'],
        'verified_at' => now(),
        'is_default' => true,
    ]);

    $method2 = AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->id,
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'New Bank'],
        'verified_at' => now(),
        'is_default' => false,
    ]);

    $method2->setAsDefault();

    expect($method1->fresh()->is_default)->toBeFalse();
    expect($method2->fresh()->is_default)->toBeTrue();
});

test('AffiliatePayoutMethod getMaskedDetails for bank transfer', function (): void {
    $method = new AffiliatePayoutMethod([
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Chase Bank', 'account_number' => '1234567890'],
    ]);

    $masked = $method->getMaskedDetails();

    expect($masked['bank_name'])->toBe('Chase Bank');
    expect($masked['account_last_4'])->toBe('****7890');
});

test('AffiliatePayoutMethod getMaskedDetails for paypal', function (): void {
    $method = new AffiliatePayoutMethod([
        'type' => PayoutMethodType::PayPal,
        'details' => ['email' => 'john.doe@example.com'],
    ]);

    $masked = $method->getMaskedDetails();

    expect($masked['email'])->toBe('jo******@example.com');
});

test('AffiliatePayoutMethod getMaskedDetails for stripe connect', function (): void {
    $method = new AffiliatePayoutMethod([
        'type' => PayoutMethodType::StripeConnect,
        'details' => ['stripe_account_id' => 'acct_1234567890abcdef'],
    ]);

    $masked = $method->getMaskedDetails();

    expect($masked['account_id'])->toBe('acct_123...');
});

test('AffiliatePayoutMethod label attribute for PayPal', function (): void {
    $method = new AffiliatePayoutMethod([
        'type' => PayoutMethodType::PayPal,
        'details' => ['email' => 'affiliate@example.com'],
    ]);

    expect($method->label)->toBe('affiliate@example.com');
});

test('AffiliatePayoutMethod label attribute for bank transfer', function (): void {
    $method = new AffiliatePayoutMethod([
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Wells Fargo'],
    ]);

    expect($method->label)->toBe('Wells Fargo');
});
