<?php

declare(strict_types=1);

use AIArmada\Vouchers\Actions\AddVoucherToWallet;
use AIArmada\Vouchers\Actions\CreateVoucher;
use AIArmada\Vouchers\Actions\RecordVoucherUsage;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\Traits\HasVouchers;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class GuardWalletUser extends Model
{
    use HasUuids;
    use HasVouchers;

    protected $table = 'users';

    protected $guarded = [];
}

function guardWalletUser(): GuardWalletUser
{
    return GuardWalletUser::create([
        'name' => 'Guard User',
        'email' => 'guard-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
}

function guardVoucher(array $attributes = []): Voucher
{
    return Voucher::create(array_merge([
        'code' => 'GUARD-' . mb_strtoupper(uniqid()),
        'name' => 'Guard Voucher',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'status' => 'active',
    ], $attributes));
}

describe('voucher update guards', function (): void {
    it('ignores protected ownership, counter, and lifecycle keys', function (): void {
        $voucher = guardVoucher(['applied_count' => 7]);

        $service = app(VoucherService::class);
        $updated = $service->update($voucher->code, [
            'name' => 'Renamed',
            'owner_type' => 'forged-type',
            'owner_id' => 'forged-id',
            'applied_count' => 999,
            'paused_at' => now()->toDateTimeString(),
            'depleted_at' => now()->toDateTimeString(),
            'last_activated_at' => now()->toDateTimeString(),
        ]);

        expect($updated->name)->toBe('Renamed');

        $fresh = $voucher->fresh();

        expect($fresh->owner_type)->toBeNull()
            ->and($fresh->owner_id)->toBeNull()
            ->and($fresh->applied_count)->toBe(7)
            ->and($fresh->paused_at)->toBeNull()
            ->and($fresh->depleted_at)->toBeNull();
    });
});

describe('voucher creation validation', function (): void {
    it('requires a type and a value', function (): void {
        expect(fn (): Voucher => CreateVoucher::run(['code' => 'NOTYPE']))
            ->toThrow(ValidationException::class);

        expect(fn (): Voucher => CreateVoucher::run(['code' => 'NOVALUE', 'type' => VoucherType::Fixed]))
            ->toThrow(ValidationException::class);
    });

    it('rejects unknown voucher types', function (): void {
        expect(fn (): Voucher => CreateVoucher::run(['type' => 'mystery', 'value' => 100]))
            ->toThrow(ValidationException::class);
    });

    it('rejects negative values and oversized percentages', function (): void {
        expect(fn (): Voucher => CreateVoucher::run(['type' => 'fixed', 'value' => -5]))
            ->toThrow(ValidationException::class);

        expect(fn (): Voucher => CreateVoucher::run(['type' => 'percentage', 'value' => 10001]))
            ->toThrow(ValidationException::class);
    });

    it('accepts boundary values', function (): void {
        $zero = CreateVoucher::run(['code' => 'BOUND-ZERO', 'type' => 'fixed', 'value' => 0]);
        $fullPercent = CreateVoucher::run(['code' => 'BOUND-PCT', 'type' => 'percentage', 'value' => 10000]);

        expect($zero->value)->toBe(0)->and($fullPercent->value)->toBe(10000);
    });

    it('rejects bad currencies, limits, and date ranges', function (): void {
        expect(fn (): Voucher => CreateVoucher::run(['type' => 'fixed', 'value' => 100, 'currency' => 'ZZZ']))
            ->toThrow(ValidationException::class);

        expect(fn (): Voucher => CreateVoucher::run(['type' => 'fixed', 'value' => 100, 'currency' => 'TOOLONG']))
            ->toThrow(ValidationException::class);

        expect(fn (): Voucher => CreateVoucher::run(['type' => 'fixed', 'value' => 100, 'max_uses' => -1]))
            ->toThrow(ValidationException::class);

        expect(fn (): Voucher => CreateVoucher::run(['type' => 'fixed', 'value' => 100, 'usage_limit_per_user' => -2]))
            ->toThrow(ValidationException::class);

        expect(fn (): Voucher => CreateVoucher::run([
            'type' => 'fixed',
            'value' => 100,
            'starts_at' => now()->addDay(),
            'expires_at' => now(),
        ]))->toThrow(ValidationException::class);

        expect(fn (): Voucher => CreateVoucher::run(['type' => 'fixed', 'value' => 100, 'starts_at' => 'not-a-date']))
            ->toThrow(ValidationException::class);
    });

    it('normalizes currency codes to uppercase', function (): void {
        $voucher = CreateVoucher::run(['code' => 'CUR-NORM', 'type' => 'fixed', 'value' => 100, 'currency' => 'usd']);

        expect($voucher->currency)->toBe('USD');
    });
});

describe('wallet claim hardening', function (): void {
    it('returns the active entry instead of failing on duplicate claims', function (): void {
        $voucher = guardVoucher();
        $user = guardWalletUser();

        $first = AddVoucherToWallet::run($voucher->code, $user);
        $second = AddVoucherToWallet::run($voucher->code, $user);

        expect($second->id)->toBe($first->id)
            ->and(VoucherWallet::where('voucher_id', $voucher->id)->count())->toBe(1);
    });

    it('allows a fresh claim after redemption', function (): void {
        $voucher = guardVoucher();
        $user = guardWalletUser();

        $first = AddVoucherToWallet::run($voucher->code, $user);
        $first->markAsRedeemed();

        $second = AddVoucherToWallet::run($voucher->code, $user);

        expect($second->id)->not->toBe($first->id)
            ->and($second->redeemed_at)->toBeNull();
    });

    it('dedupes service-level wallet adds', function (): void {
        $voucher = guardVoucher();
        $user = guardWalletUser();
        $service = app(VoucherService::class);

        $first = $service->addToWallet($voucher->code, $user);
        $second = $service->addToWallet($voucher->code, $user);

        expect($second->id)->toBe($first->id);
    });

    it('sets claim and redeem timestamps exactly once', function (): void {
        $voucher = guardVoucher();
        $user = guardWalletUser();

        $entry = AddVoucherToWallet::run($voucher->code, $user);
        $stale = VoucherWallet::query()->whereKey($entry->id)->first();

        expect($stale->redeemed_at)->toBeNull();

        $entry->markAsRedeemed();
        $winnerAt = (string) $entry->fresh()->redeemed_at;

        // A stale copy racing the winner syncs to the winner timestamp.
        $stale->markAsRedeemed();

        expect((string) $entry->fresh()->redeemed_at)->toBe($winnerAt)
            ->and((string) $stale->redeemed_at)->toBe($winnerAt);
    });
});

describe('usage recording guards', function (): void {
    it('rejects usage in a different currency than the voucher', function (): void {
        $voucher = guardVoucher(['currency' => 'MYR']);

        expect(fn () => RecordVoucherUsage::run($voucher->code, Money::USD(100)))
            ->toThrow(ValidationException::class);

        expect(VoucherUsage::where('voucher_id', $voucher->id)->count())->toBe(0);
    });

    it('dedupes manual redemptions sharing a reference', function (): void {
        $voucher = guardVoucher(['allows_manual_redemption' => true]);
        $service = app(VoucherService::class);

        $service->redeemManually(code: $voucher->code, discountAmount: Money::MYR(100), reference: 'counter-7');
        $service->redeemManually(code: $voucher->code, discountAmount: Money::MYR(100), reference: 'counter-7');

        expect(VoucherUsage::where('voucher_id', $voucher->id)->count())->toBe(1);
    });
});

describe('usage history bound', function (): void {
    it('caps returned rows', function (): void {
        $voucher = guardVoucher();
        $service = app(VoucherService::class);

        for ($i = 0; $i < 5; $i++) {
            RecordVoucherUsage::run($voucher->code, Money::MYR(10), metadata: ['idempotency_key' => "history-{$i}"]);
        }

        expect($service->getUsageHistory($voucher->code, 2))->toHaveCount(2)
            ->and($service->getUsageHistory($voucher->code))->toHaveCount(5)
            ->and($service->getUsageHistory('missing-code'))->toBeEmpty();
    });
});

describe('voucher session reservations', function (): void {
    it('tracks and releases sessions without losing entries', function (): void {
        Cache::flush();

        $voucher = guardVoucher();
        $service = app(VoucherService::class);

        $service->reserve($voucher->code, 'session-a');
        $service->reserve($voucher->code, 'session-b');

        expect(Cache::get("voucher_reservation:{$voucher->id}:session-a"))->not->toBeNull()
            ->and(Cache::get("voucher_reservation:{$voucher->id}:session-b"))->not->toBeNull();

        $service->release($voucher->code, 'session-a');

        expect(Cache::get("voucher_reservation:{$voucher->id}:session-a"))->toBeNull()
            ->and(Cache::get("voucher_reservation:{$voucher->id}:session-b"))->not->toBeNull();

        $service->release($voucher->code);

        expect(Cache::get("voucher_reservation:{$voucher->id}:session-b"))->toBeNull();

        Cache::flush();
    });
});
