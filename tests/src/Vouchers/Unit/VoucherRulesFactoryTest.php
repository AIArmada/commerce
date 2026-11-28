<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Support\VoucherRulesFactory;

test('voucher rules factory creates rules for voucher key', function (): void {
    $factory = new VoucherRulesFactory;

    $rules = $factory->createRules('voucher', ['voucher_code' => 'TEST', 'voucher_id' => 'voucher-test']);

    expect($rules)->toBeArray()
        ->and(count($rules))->toBe(1);
});

test('voucher rules factory throws for invalid voucher code', function (): void {
    $factory = new VoucherRulesFactory;

    expect(fn () => $factory->createRules('voucher', []))->toThrow(InvalidArgumentException::class);
});

test('voucher rules factory can create rules for voucher key', function (): void {
    $factory = new VoucherRulesFactory;

    expect($factory->canCreateRules('voucher'))->toBeTrue()
        ->and($factory->canCreateRules('other'))->toBeFalse();
});

test('voucher rules factory get available keys', function (): void {
    $factory = new VoucherRulesFactory;

    $keys = $factory->getAvailableKeys();

    expect($keys)->toContain('voucher');
});

test('voucher rules factory get fallback', function (): void {
    $factory = new VoucherRulesFactory;

    expect($factory->getFallback())->toBeNull();
});

test('voucher rules factory with fallback', function (): void {
    $fallback = new class implements RulesFactoryInterface
    {
        public function createRules(string $key, array $metadata = []): array
        {
            return [['fallback', 'rule']];
        }

        public function canCreateRules(string $key): bool
        {
            return $key === 'fallback';
        }

        public function getAvailableKeys(): array
        {
            return ['fallback'];
        }
    };

    $factory = new VoucherRulesFactory($fallback);

    expect($factory->getFallback())->toBe($fallback)
        ->and($factory->canCreateRules('fallback'))->toBeTrue()
        ->and($factory->createRules('fallback'))->toBe([['fallback', 'rule']])
        ->and($factory->getAvailableKeys())->toContain('voucher');
});

test('voucher rule refuses missing identity metadata', function (): void {
    $factory = new VoucherRulesFactory;

    expect(fn () => $factory->createRules('voucher', ['voucher_code' => 'TEST']))
        ->toThrow(InvalidArgumentException::class);
});

test('voucher rule refuses a different voucher with the same code', function (): void {
    $cart = new Cart(new InMemoryStorage, 'voucher-rule-identity');
    $factory = new VoucherRulesFactory;
    $rule = $factory->createRules('voucher', ['voucher_code' => 'TEST', 'voucher_id' => 'selected'])[0];

    Voucher::shouldReceive('validate')->with('TEST', $cart)->twice()->andReturn(
        VoucherValidationResult::valid(['voucher' => ['id' => 'replacement']]),
        VoucherValidationResult::valid(['voucher' => ['id' => 'selected']]),
    );

    expect($rule($cart))->toBeFalse()
        ->and($rule($cart))->toBeTrue();
});
