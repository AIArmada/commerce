<?php

declare(strict_types=1);

use AIArmada\Vouchers\AI\VoucherMatch;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;

/**
 * Helper to create a Voucher model without database.
 *
 * @param  array<string, mixed>  $attributes
 */
function createVoucher(array $attributes = []): Voucher
{
    $voucher = new Voucher;
    $voucher->forceFill(array_merge([
        'id' => 'test-voucher-'.uniqid(),
        'code' => 'TEST'.strtoupper(substr(uniqid(), 0, 6)),
        'name' => 'Test Voucher',
        'type' => VoucherType::Percentage,
        'value' => 1000,
        'currency' => 'MYR',
        'status' => VoucherStatus::Active,
    ], $attributes));

    return $voucher;
}

describe('VoucherMatch', function (): void {
    describe('construction', function (): void {
        it('can be constructed with all parameters', function (): void {
            $voucher = createVoucher();

            $match = new VoucherMatch(
                voucher: $voucher,
                matchScore: 0.85,
                matchReasons: ['reason' => 'test'],
                alternatives: [['id' => 1, 'code' => 'ALT1']],
            );

            expect($match->voucher)->toBe($voucher)
                ->and($match->matchScore)->toBe(0.85)
                ->and($match->matchReasons)->toBe(['reason' => 'test'])
                ->and($match->alternatives)->toBe([['id' => 1, 'code' => 'ALT1']]);
        });

        it('can be constructed with minimal parameters', function (): void {
            $match = new VoucherMatch(
                voucher: null,
                matchScore: 0.0,
            );

            expect($match->voucher)->toBeNull()
                ->and($match->matchScore)->toBe(0.0)
                ->and($match->matchReasons)->toBe([])
                ->and($match->alternatives)->toBe([]);
        });
    });

    describe('static constructors', function (): void {
        it('creates empty match with none()', function (): void {
            $match = VoucherMatch::none();

            expect($match->voucher)->toBeNull()
                ->and($match->matchScore)->toBe(0.0)
                ->and($match->matchReasons)->toHaveKey('no_match')
                ->and($match->matchReasons['no_match'])->toBe('No suitable voucher found');
        });

        it('creates perfect match with perfect()', function (): void {
            $voucher = createVoucher();

            $match = VoucherMatch::perfect($voucher, ['extra' => 'reason']);

            expect($match->voucher)->toBe($voucher)
                ->and($match->matchScore)->toBe(1.0)
                ->and($match->matchReasons)->toHaveKey('match_type')
                ->and($match->matchReasons['match_type'])->toBe('perfect')
                ->and($match->matchReasons)->toHaveKey('extra');
        });

        it('creates good match with good()', function (): void {
            $voucher = createVoucher();

            $match = VoucherMatch::good($voucher, 0.8, ['reason' => 'value']);

            expect($match->voucher)->toBe($voucher)
                ->and($match->matchScore)->toBe(0.8)
                ->and($match->matchReasons['match_type'])->toBe('good');
        });

        it('uses default score of 0.75 for good()', function (): void {
            $voucher = createVoucher();

            $match = VoucherMatch::good($voucher);

            expect($match->matchScore)->toBe(0.75);
        });
    });

    describe('hasMatch', function (): void {
        it('returns true when voucher is present', function (): void {
            $voucher = createVoucher();
            $match = new VoucherMatch($voucher, 0.5);

            expect($match->hasMatch())->toBeTrue();
        });

        it('returns false when voucher is null', function (): void {
            $match = VoucherMatch::none();

            expect($match->hasMatch())->toBeFalse();
        });
    });

    describe('isStrongMatch', function (): void {
        it('returns true for score >= 0.7', function (): void {
            $voucher = createVoucher();

            $match70 = new VoucherMatch($voucher, 0.7);
            $match85 = new VoucherMatch($voucher, 0.85);
            $match100 = new VoucherMatch($voucher, 1.0);

            expect($match70->isStrongMatch())->toBeTrue()
                ->and($match85->isStrongMatch())->toBeTrue()
                ->and($match100->isStrongMatch())->toBeTrue();
        });

        it('returns false for score < 0.7', function (): void {
            $voucher = createVoucher();

            $match = new VoucherMatch($voucher, 0.69);

            expect($match->isStrongMatch())->toBeFalse();
        });
    });

    describe('isWeakMatch', function (): void {
        it('returns true for score < 0.5 with match', function (): void {
            $voucher = createVoucher();

            $match = new VoucherMatch($voucher, 0.49);

            expect($match->isWeakMatch())->toBeTrue();
        });

        it('returns false for score >= 0.5', function (): void {
            $voucher = createVoucher();

            $match = new VoucherMatch($voucher, 0.5);

            expect($match->isWeakMatch())->toBeFalse();
        });

        it('returns false when no match', function (): void {
            $match = VoucherMatch::none();

            expect($match->isWeakMatch())->toBeFalse();
        });
    });

    describe('hasAlternatives', function (): void {
        it('returns true when alternatives exist', function (): void {
            $match = new VoucherMatch(
                voucher: null,
                matchScore: 0.0,
                alternatives: [['id' => 1]],
            );

            expect($match->hasAlternatives())->toBeTrue();
        });

        it('returns false when no alternatives', function (): void {
            $match = VoucherMatch::none();

            expect($match->hasAlternatives())->toBeFalse();
        });
    });

    describe('getCode', function (): void {
        it('returns voucher code when matched', function (): void {
            $voucher = createVoucher(['code' => 'DISCOUNT10']);

            $match = new VoucherMatch($voucher, 0.9);

            expect($match->getCode())->toBe('DISCOUNT10');
        });

        it('returns null when no match', function (): void {
            $match = VoucherMatch::none();

            expect($match->getCode())->toBeNull();
        });
    });

    describe('getDiscountAmount', function (): void {
        it('returns voucher value when matched', function (): void {
            $voucher = createVoucher(['value' => 1000]);

            $match = new VoucherMatch($voucher, 0.9);

            expect($match->getDiscountAmount())->toBe(1000);
        });

        it('returns null when no match', function (): void {
            $match = VoucherMatch::none();

            expect($match->getDiscountAmount())->toBeNull();
        });
    });

    describe('getTopReasons', function (): void {
        it('returns limited reasons', function (): void {
            $match = new VoucherMatch(
                voucher: null,
                matchScore: 0.0,
                matchReasons: [
                    'reason1' => 'value1',
                    'reason2' => 'value2',
                    'reason3' => 'value3',
                    'reason4' => 'value4',
                    'reason5' => 'value5',
                ],
            );

            $topReasons = $match->getTopReasons(3);

            expect($topReasons)->toHaveCount(3)
                ->and($topReasons)->toHaveKeys(['reason1', 'reason2', 'reason3']);
        });

        it('returns all reasons if fewer than limit', function (): void {
            $match = new VoucherMatch(
                voucher: null,
                matchScore: 0.0,
                matchReasons: ['only' => 'one'],
            );

            $topReasons = $match->getTopReasons(5);

            expect($topReasons)->toHaveCount(1);
        });

        it('uses default limit of 3', function (): void {
            $match = new VoucherMatch(
                voucher: null,
                matchScore: 0.0,
                matchReasons: [
                    'a' => 1,
                    'b' => 2,
                    'c' => 3,
                    'd' => 4,
                ],
            );

            $topReasons = $match->getTopReasons();

            expect($topReasons)->toHaveCount(3);
        });
    });

    describe('getBestAlternative', function (): void {
        it('returns first alternative', function (): void {
            $match = new VoucherMatch(
                voucher: null,
                matchScore: 0.0,
                alternatives: [
                    ['id' => 'first', 'score' => 0.8],
                    ['id' => 'second', 'score' => 0.6],
                ],
            );

            $best = $match->getBestAlternative();

            expect($best)->toBe(['id' => 'first', 'score' => 0.8]);
        });

        it('returns null when no alternatives', function (): void {
            $match = VoucherMatch::none();

            expect($match->getBestAlternative())->toBeNull();
        });
    });

    describe('getSummary', function (): void {
        it('returns summary for matched voucher', function (): void {
            $voucher = createVoucher(['code' => 'SUMMER20']);

            $match = new VoucherMatch($voucher, 0.85);

            $summary = $match->getSummary();

            expect($summary)->toBe('Matched: SUMMER20 (score: 85%)');
        });

        it('returns no match message when no voucher', function (): void {
            $match = VoucherMatch::none();

            expect($match->getSummary())->toBe('No voucher match found');
        });

        it('rounds score correctly', function (): void {
            $voucher = createVoucher(['code' => 'TEST']);

            $match = new VoucherMatch($voucher, 0.666);

            expect($match->getSummary())->toBe('Matched: TEST (score: 67%)');
        });
    });

    describe('toArray', function (): void {
        it('converts to array with all fields', function (): void {
            $voucher = createVoucher([
                'id' => 'voucher-123',
                'code' => 'CODE123',
            ]);

            $match = new VoucherMatch(
                voucher: $voucher,
                matchScore: 0.75,
                matchReasons: ['type' => 'good'],
                alternatives: [['alt' => 1]],
            );

            $array = $match->toArray();

            expect($array)->toHaveKeys([
                'voucher_id',
                'voucher_code',
                'match_score',
                'match_reasons',
                'alternatives',
                'has_match',
                'is_strong_match',
            ])
                ->and($array['voucher_code'])->toBe('CODE123')
                ->and($array['match_score'])->toBe(0.75)
                ->and($array['has_match'])->toBeTrue()
                ->and($array['is_strong_match'])->toBeTrue();
        });

        it('handles null voucher in array', function (): void {
            $match = VoucherMatch::none();
            $array = $match->toArray();

            expect($array['voucher_id'])->toBeNull()
                ->and($array['voucher_code'])->toBeNull()
                ->and($array['has_match'])->toBeFalse();
        });
    });
});
