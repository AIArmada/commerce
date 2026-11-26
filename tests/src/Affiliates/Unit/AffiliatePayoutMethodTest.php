<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutMethodAdministration;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

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

test('AffiliatePayoutMethod stores an affiliate identity without an ORM relationship', function (): void {
    $method = new AffiliatePayoutMethod;

    expect(method_exists($method, 'affiliate'))->toBeFalse();
});

test('Payout method administration verifies a method', function (): void {
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

    app(PayoutMethodAdministration::class)->verify((string) $affiliate->getKey(), (string) $method->getKey());

    expect($method->fresh()->isVerified())->toBeTrue();
    expect($method->fresh()->verified_at)->not->toBeNull();
});

test('Payout method administration selects a single default', function (): void {
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

    app(PayoutMethodAdministration::class)->selectDefault((string) $affiliate->getKey(), (string) $method2->getKey());

    expect($method1->fresh()->is_default)->toBeFalse();
    expect($method2->fresh()->is_default)->toBeTrue();

    $other = Affiliate::create([
        'code' => 'PAY005',
        'name' => 'Other affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $administration = app(PayoutMethodAdministration::class);
    expect(fn () => $administration->selectDefault((string) $other->getKey(), (string) $method2->getKey()))->toThrow(ModelNotFoundException::class);
    expect(fn () => $administration->verify((string) $other->getKey(), (string) $method2->getKey()))->toThrow(ModelNotFoundException::class);
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

test('AffiliatePayoutMethod hydrates the manual type', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAY004',
        'name' => 'Manual Payout Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $method = AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => 'manual',
        'details' => ['account_ref' => 'operator-account'],
        'is_default' => true,
    ]);

    expect($method->fresh()->type)->toBe(PayoutMethodType::Manual);
});

test('payout method changes cannot redirect an unresolved provider operation', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAYOUT-DESTINATION-' . uniqid(),
        'name' => 'Payout destination',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
    $methods = app(PayoutMethodAdministration::class);
    $default = $methods->create($affiliate->id, 'paypal', ['email' => 'first@example.com'], true);
    $other = $methods->create($affiliate->id, 'paypal', ['email' => 'second@example.com']);
    $operation = AffiliatePayoutOperation::create([
        'affiliate_id' => $affiliate->id,
        'operation_key' => 'method-guard-' . $affiliate->id,
        'status' => 'unknown',
        'amount_minor' => 5000,
        'currency' => 'USD',
        'claimed_at' => now(),
    ]);

    expect(fn () => $methods->selectDefault($affiliate->id, $other))->toThrow(ValidationException::class)
        ->and(fn () => $methods->replace($affiliate->id, $default, 'paypal', ['email' => 'changed@example.com']))->toThrow(ValidationException::class)
        ->and(fn () => $methods->remove($affiliate->id, $default))->toThrow(ValidationException::class)
        ->and(fn () => $methods->setDefault($affiliate->id, 'paypal', 'Changed', 'changed@example.com'))->toThrow(ValidationException::class)
        ->and(AffiliatePayoutMethod::query()->findOrFail($default)->is_default)->toBeTrue()
        ->and(AffiliatePayoutMethod::query()->findOrFail($default)->details['email'])->toBe('first@example.com');

    $operation->update(['status' => 'failed']);
    $methods->selectDefault($affiliate->id, $other);

    expect(AffiliatePayoutMethod::query()->findOrFail($other)->is_default)->toBeTrue();
});
