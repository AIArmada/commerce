<?php

declare(strict_types=1);

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherTransaction;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

describe('VoucherTransaction Model', function (): void {
    describe('class structure', function (): void {
        it('is a final class', function (): void {
            $reflection = new ReflectionClass(VoucherTransaction::class);
            expect($reflection->isFinal())->toBeTrue();
        });

        it('uses HasUuids trait', function (): void {
            $traits = class_uses_recursive(VoucherTransaction::class);
            expect($traits)->toContain(HasUuids::class);
        });

        it('extends Eloquent Model', function (): void {
            $transaction = new VoucherTransaction;
            expect($transaction)->toBeInstanceOf(Model::class);
        });
    });

    describe('fillable attributes', function (): void {
        it('has correct fillable fields', function (): void {
            $transaction = new VoucherTransaction;
            $expected = [
                'voucher_id',
                'voucher_wallet_id',
                'walletable_type',
                'walletable_id',
                'amount',
                'balance',
                'type',
                'currency',
                'description',
                'metadata',
            ];

            expect($transaction->getFillable())->toBe($expected);
        });

        it('can be mass assigned', function (): void {
            $transaction = new VoucherTransaction([
                'voucher_id' => 'voucher-123',
                'voucher_wallet_id' => 'wallet-456',
                'walletable_type' => 'App\\Models\\User',
                'walletable_id' => 'user-789',
                'amount' => 1000,
                'balance' => 5000,
                'type' => 'credit',
                'currency' => 'MYR',
                'description' => 'Test transaction',
                'metadata' => ['key' => 'value'],
            ]);

            expect($transaction->voucher_id)->toBe('voucher-123')
                ->and($transaction->voucher_wallet_id)->toBe('wallet-456')
                ->and($transaction->walletable_type)->toBe('App\\Models\\User')
                ->and($transaction->walletable_id)->toBe('user-789')
                ->and($transaction->amount)->toBe(1000)
                ->and($transaction->balance)->toBe(5000)
                ->and($transaction->type)->toBe('credit')
                ->and($transaction->currency)->toBe('MYR')
                ->and($transaction->description)->toBe('Test transaction')
                ->and($transaction->metadata)->toBe(['key' => 'value']);
        });

        it('allows nullable fields to be null', function (): void {
            $transaction = new VoucherTransaction([
                'voucher_id' => 'voucher-123',
                'amount' => 1000,
                'balance' => 1000,
                'type' => 'credit',
                'currency' => 'MYR',
            ]);

            expect($transaction->voucher_wallet_id)->toBeNull()
                ->and($transaction->walletable_type)->toBeNull()
                ->and($transaction->walletable_id)->toBeNull()
                ->and($transaction->description)->toBeNull()
                ->and($transaction->metadata)->toBeNull();
        });
    });

    describe('relationships', function (): void {
        it('defines voucher relationship as BelongsTo', function (): void {
            $transaction = new VoucherTransaction;
            $relation = $transaction->voucher();

            expect($relation)->toBeInstanceOf(BelongsTo::class)
                ->and($relation->getRelated())->toBeInstanceOf(Voucher::class);
        });

        it('defines voucherWallet relationship as BelongsTo', function (): void {
            $transaction = new VoucherTransaction;
            $relation = $transaction->voucherWallet();

            expect($relation)->toBeInstanceOf(BelongsTo::class)
                ->and($relation->getRelated())->toBeInstanceOf(VoucherWallet::class);
        });

        it('defines walletable relationship as MorphTo', function (): void {
            $transaction = new VoucherTransaction;
            $relation = $transaction->walletable();

            expect($relation)->toBeInstanceOf(MorphTo::class);
        });
    });

    describe('casts', function (): void {
        it('has correct cast definitions', function (): void {
            $transaction = new VoucherTransaction;
            $casts = $transaction->getCasts();

            expect($casts['amount'])->toBe('integer')
                ->and($casts['balance'])->toBe('integer')
                ->and($casts['currency'])->toBe('string')
                ->and($casts['metadata'])->toBe('array');
        });

        it('casts amount to integer', function (): void {
            $transaction = new VoucherTransaction;
            $transaction->amount = '2500';

            expect($transaction->amount)->toBeInt()
                ->and($transaction->amount)->toBe(2500);
        });

        it('casts balance to integer', function (): void {
            $transaction = new VoucherTransaction;
            $transaction->balance = '7500';

            expect($transaction->balance)->toBeInt()
                ->and($transaction->balance)->toBe(7500);
        });

        it('casts metadata to array', function (): void {
            $transaction = new VoucherTransaction;
            $transaction->metadata = ['transaction_ref' => 'TXN001', 'source' => 'api'];

            expect($transaction->metadata)->toBeArray()
                ->and($transaction->metadata['transaction_ref'])->toBe('TXN001')
                ->and($transaction->metadata['source'])->toBe('api');
        });
    });
});
