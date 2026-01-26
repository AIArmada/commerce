<?php

declare(strict_types=1);

use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Services\VoucherValidator;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Paused;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

test('validates valid voucher', function (): void {
    $voucher = Voucher::create([
        'code' => 'VALID',
        'name' => 'Valid Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    $validator = app(VoucherValidator::class);
    $cart = ['total' => 100.0];

    $result = $validator->validate('valid', $cart);

    expect($result->isValid)->toBeTrue();
});

test('validates invalid voucher code', function (): void {
    $validator = app(VoucherValidator::class);
    $cart = ['total' => 100.0];

    $result = $validator->validate('INVALID', $cart);

    expect($result->isValid)->toBeFalse()
        ->and($result->reason)->toBe('Voucher not found.');
});

test('validates inactive voucher', function (): void {
    $voucher = Voucher::create([
        'code' => 'INACTIVE',
        'name' => 'Inactive Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'MYR',
        'status' => Paused::class,
    ]);

    $validator = app(VoucherValidator::class);
    $cart = ['total' => 100.0];

    $result = $validator->validate('inactive', $cart);

    expect($result->isValid)->toBeFalse()
        ->and($result->reason)->toBe('Voucher is paused.');
});

test('validates voucher not started', function (): void {
    $voucher = Voucher::create([
        'code' => 'NOTSTARTED',
        'name' => 'Not Started Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
        'starts_at' => now()->addDay(),
    ]);

    $validator = app(VoucherValidator::class);
    $cart = ['total' => 100.0];

    $result = $validator->validate('notstarted', $cart);

    expect($result->isValid)->toBeFalse()
        ->and($result->reason)->toBe('Voucher is not yet available.');
});

test('validates expired voucher', function (): void {
    $voucher = Voucher::create([
        'code' => 'EXPIRED',
        'name' => 'Expired Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
        'expires_at' => now()->subDay(),
    ]);

    $validator = app(VoucherValidator::class);
    $cart = ['total' => 100.0];

    $result = $validator->validate('expired', $cart);

    expect($result->isValid)->toBeFalse()
        ->and($result->reason)->toBe('Voucher has expired.');
});

test('validates voucher with usage limit reached', function (): void {
    Config::set('vouchers.validation.check_global_limit', true);

    $voucher = Voucher::create([
        'code' => 'LIMITED',
        'name' => 'Limited Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
        'usage_limit' => 1,
    ]);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 10,
        'currency' => 'MYR',
        'used_at' => now(),
    ]);

    $validator = app(VoucherValidator::class);
    $cart = ['total' => 100.0];

    $result = $validator->validate('limited', $cart);

    expect($result->isValid)->toBeFalse()
        ->and($result->reason)->toBe('Voucher usage limit has been reached.');
});

test('validates voucher with per-user limit reached', function (): void {
    Config::set('vouchers.validation.check_user_limit', true);

    $user = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass()
        {
            return 'User';
        }

        public function getKey()
        {
            return 1;
        }
    };

    Auth::shouldReceive('user')->andReturn($user);

    $voucher = Voucher::create([
        'code' => 'USERLIMIT',
        'name' => 'User Limit Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
        'usage_limit_per_user' => 1,
    ]);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 10,
        'currency' => 'MYR',
        'used_at' => now(),
        'redeemed_by_id' => 1,
        'redeemed_by_type' => 'User',
    ]);

    $validator = app(VoucherValidator::class);
    $cart = ['total' => 100.0];

    $result = $validator->validate('userlimit', $cart);

    expect($result->isValid)->toBeFalse()
        ->and($result->reason)->toBe('You have already used this voucher the maximum number of times.');
});

test('validates voucher with minimum cart value not met', function (): void {
    Config::set('vouchers.validation.check_min_cart_value', true);

    $voucher = Voucher::create([
        'code' => 'MINCART',
        'name' => 'Min Cart Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
        'min_cart_value' => 20000, // 20000 cents = $200.00
    ]);

    $validator = app(VoucherValidator::class);
    $cart = ['total' => 10000]; // 10000 cents = $100.00

    $result = $validator->validate('mincart', $cart);

    expect($result->isValid)->toBeFalse()
        ->and($result->reason)->toContain('Minimum cart value');
});

test('validates with object cart', function (): void {
    $voucher = Voucher::create([
        'code' => 'OBJCART',
        'name' => 'Object Cart Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
        'min_cart_value' => 5000, // 5000 cents = $50.00
    ]);

    $cart = new class
    {
        public function getRawSubtotalWithoutConditions()
        {
            return 10000; // 10000 cents = $100.00
        }
    };

    $validator = app(VoucherValidator::class);

    $result = $validator->validate('objcart', $cart);

    expect($result->isValid)->toBeTrue();
});

test('normalizes code with uppercase', function (): void {
    Config::set('vouchers.code.auto_uppercase', true);

    $voucher = Voucher::create([
        'code' => 'LOWERCASE',
        'name' => 'Lowercase Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    $validator = app(VoucherValidator::class);
    $cart = ['total' => 100.0];

    $result = $validator->validate('lowercase', $cart);

    expect($result->isValid)->toBeTrue();
});

test('normalizes code without uppercase', function (): void {
    Config::set('vouchers.code.auto_uppercase', false);

    $voucher = Voucher::create([
        'code' => 'lowercase',
        'name' => 'Lowercase Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    $validator = app(VoucherValidator::class);
    $cart = ['total' => 100.0];

    $result = $validator->validate('lowercase', $cart);

    expect($result->isValid)->toBeTrue();
});
