<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Support\VoucherRulesFactory;

test('voucher rules factory creates rules for voucher key', function (): void {
    $factory = new VoucherRulesFactory;

    $snapshot = VoucherData::fromArray(['id' => 'voucher-test', 'code' => 'TEST'])->toArray();
    $rules = $factory->createRules('voucher', ['voucher_code' => 'TEST', 'voucher_id' => 'voucher-test', 'voucher_data' => $snapshot]);

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
    $snapshot = VoucherData::fromArray(['id' => 'selected', 'code' => 'TEST', 'value' => 100])->toArray();
    $rule = $factory->createRules('voucher', ['voucher_code' => 'TEST', 'voucher_id' => 'selected', 'voucher_data' => $snapshot])[0];

    Voucher::shouldReceive('validate')->with('TEST', $cart)->times(3)->andReturn(
        VoucherValidationResult::valid(['voucher' => [...$snapshot, 'id' => 'replacement']]),
        VoucherValidationResult::valid(['voucher' => [...$snapshot, 'value' => 50]]),
        VoucherValidationResult::valid(['voucher' => $snapshot]),
    );

    expect($rule($cart))->toBeFalse()
        ->and($rule($cart))->toBeFalse()
        ->and($rule($cart))->toBeTrue();
});

test('voucher rule refuses missing or mismatched snapshots', function (): void {
    $factory = new VoucherRulesFactory;
    $snapshot = VoucherData::fromArray(['id' => 'other', 'code' => 'TEST'])->toArray();

    expect(fn () => $factory->createRules('voucher', ['voucher_code' => 'TEST', 'voucher_id' => 'selected']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $factory->createRules('voucher', ['voucher_code' => 'TEST', 'voucher_id' => 'selected', 'voucher_data' => $snapshot]))
        ->toThrow(InvalidArgumentException::class);
});
