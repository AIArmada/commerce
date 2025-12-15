<?php

declare(strict_types=1);

use AIArmada\Vouchers\Data\VoucherValidationResult;

describe('VoucherValidationResult', function (): void {
    describe('construction', function (): void {
        it('can be constructed with all parameters', function (): void {
            $result = new VoucherValidationResult(
                isValid: false,
                reason: 'Voucher expired',
                details: ['expired_at' => '2024-01-01'],
            );

            expect($result->isValid)->toBeFalse()
                ->and($result->reason)->toBe('Voucher expired')
                ->and($result->details)->toBe(['expired_at' => '2024-01-01']);
        });

        it('can be constructed with minimal parameters', function (): void {
            $result = new VoucherValidationResult(isValid: true);

            expect($result->isValid)->toBeTrue()
                ->and($result->reason)->toBeNull()
                ->and($result->details)->toBeNull();
        });
    });

    describe('static constructors', function (): void {
        it('creates valid result', function (): void {
            $result = VoucherValidationResult::valid();

            expect($result->isValid)->toBeTrue()
                ->and($result->reason)->toBeNull()
                ->and($result->details)->toBeNull();
        });

        it('creates invalid result with reason', function (): void {
            $result = VoucherValidationResult::invalid('Code not found');

            expect($result->isValid)->toBeFalse()
                ->and($result->reason)->toBe('Code not found')
                ->and($result->details)->toBeNull();
        });

        it('creates invalid result with details', function (): void {
            $result = VoucherValidationResult::invalid('Min cart value not met', [
                'required' => 10000,
                'actual' => 5000,
            ]);

            expect($result->isValid)->toBeFalse()
                ->and($result->reason)->toBe('Min cart value not met')
                ->and($result->details)->toBe(['required' => 10000, 'actual' => 5000]);
        });

        it('sets details to null for empty array', function (): void {
            $result = VoucherValidationResult::invalid('Some reason', []);

            expect($result->details)->toBeNull();
        });
    });

    describe('passed and failed', function (): void {
        it('passed returns true for valid result', function (): void {
            $result = VoucherValidationResult::valid();

            expect($result->passed())->toBeTrue();
        });

        it('passed returns false for invalid result', function (): void {
            $result = VoucherValidationResult::invalid('Error');

            expect($result->passed())->toBeFalse();
        });

        it('failed returns true for invalid result', function (): void {
            $result = VoucherValidationResult::invalid('Error');

            expect($result->failed())->toBeTrue();
        });

        it('failed returns false for valid result', function (): void {
            $result = VoucherValidationResult::valid();

            expect($result->failed())->toBeFalse();
        });
    });

    describe('getFailureReason', function (): void {
        it('returns reason for invalid result', function (): void {
            $result = VoucherValidationResult::invalid('Usage limit exceeded');

            expect($result->getFailureReason())->toBe('Usage limit exceeded');
        });

        it('returns null for valid result', function (): void {
            $result = VoucherValidationResult::valid();

            expect($result->getFailureReason())->toBeNull();
        });
    });

    describe('getDetail', function (): void {
        it('returns detail value by key', function (): void {
            $result = VoucherValidationResult::invalid('Error', [
                'min_value' => 5000,
                'max_uses' => 10,
            ]);

            expect($result->getDetail('min_value'))->toBe(5000)
                ->and($result->getDetail('max_uses'))->toBe(10);
        });

        it('returns default for missing key', function (): void {
            $result = VoucherValidationResult::invalid('Error', ['key' => 'value']);

            expect($result->getDetail('missing'))->toBeNull()
                ->and($result->getDetail('missing', 'default'))->toBe('default');
        });

        it('returns default when details is null', function (): void {
            $result = VoucherValidationResult::valid();

            expect($result->getDetail('any_key'))->toBeNull()
                ->and($result->getDetail('any_key', 'fallback'))->toBe('fallback');
        });

        it('handles various detail types', function (): void {
            $result = VoucherValidationResult::invalid('Error', [
                'string' => 'value',
                'int' => 42,
                'float' => 3.14,
                'bool' => true,
                'array' => [1, 2, 3],
                'null' => null,
            ]);

            expect($result->getDetail('string'))->toBe('value')
                ->and($result->getDetail('int'))->toBe(42)
                ->and($result->getDetail('float'))->toBe(3.14)
                ->and($result->getDetail('bool'))->toBeTrue()
                ->and($result->getDetail('array'))->toBe([1, 2, 3])
                ->and($result->getDetail('null'))->toBeNull();
        });
    });

    describe('data transformation', function (): void {
        it('can convert to array', function (): void {
            $result = VoucherValidationResult::invalid('Error', ['key' => 'value']);

            $array = $result->toArray();

            expect($array)->toBeArray()
                ->and($array)->toHaveKeys(['is_valid', 'reason', 'details'])
                ->and($array['is_valid'])->toBeFalse()
                ->and($array['reason'])->toBe('Error')
                ->and($array['details'])->toBe(['key' => 'value']);
        });

        it('can convert valid result to array', function (): void {
            $result = VoucherValidationResult::valid();

            $array = $result->toArray();

            expect($array['is_valid'])->toBeTrue()
                ->and($array['reason'])->toBeNull();
        });

        it('can convert to JSON', function (): void {
            $result = VoucherValidationResult::invalid('Error', ['amount' => 100]);

            $json = $result->toJson();

            expect($json)->toBeString()
                ->and(json_decode($json, true))->toBe([
                    'is_valid' => false,
                    'reason' => 'Error',
                    'details' => ['amount' => 100],
                ]);
        });
    });

    describe('common validation scenarios', function (): void {
        it('handles voucher not found', function (): void {
            $result = VoucherValidationResult::invalid('Voucher not found', [
                'code' => 'INVALID123',
            ]);

            expect($result->failed())->toBeTrue()
                ->and($result->getDetail('code'))->toBe('INVALID123');
        });

        it('handles expired voucher', function (): void {
            $result = VoucherValidationResult::invalid('Voucher has expired', [
                'expired_at' => '2024-01-01 00:00:00',
            ]);

            expect($result->failed())->toBeTrue()
                ->and($result->getDetail('expired_at'))->toBe('2024-01-01 00:00:00');
        });

        it('handles min cart value not met', function (): void {
            $result = VoucherValidationResult::invalid('Minimum cart value not met', [
                'min_required' => 10000,
                'cart_value' => 5000,
                'currency' => 'MYR',
            ]);

            expect($result->failed())->toBeTrue()
                ->and($result->getDetail('min_required'))->toBe(10000)
                ->and($result->getDetail('cart_value'))->toBe(5000);
        });

        it('handles usage limit exceeded', function (): void {
            $result = VoucherValidationResult::invalid('Usage limit exceeded', [
                'limit' => 100,
                'used' => 100,
            ]);

            expect($result->failed())->toBeTrue()
                ->and($result->getDetail('limit'))->toBe(100);
        });

        it('handles user limit exceeded', function (): void {
            $result = VoucherValidationResult::invalid('User usage limit exceeded', [
                'user_limit' => 1,
                'user_uses' => 1,
                'user_id' => 'user-123',
            ]);

            expect($result->failed())->toBeTrue()
                ->and($result->getDetail('user_id'))->toBe('user-123');
        });
    });
});
