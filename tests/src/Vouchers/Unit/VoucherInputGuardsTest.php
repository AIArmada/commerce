<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Exceptions\VoucherUsageLimitException;
use AIArmada\Vouchers\Features\Lifecycle\Actions\CreateVoucher;
use AIArmada\Vouchers\Features\Lifecycle\Actions\RecordVoucherUsage;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherUpdater;
use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher;
use AIArmada\Vouchers\Features\Lifecycle\Models\VoucherUsage;
use AIArmada\Vouchers\Features\Wallet\Actions\AddVoucherToWallet;
use AIArmada\Vouchers\Features\Wallet\EntryPoints\Traits\HasVoucherWallet;
use AIArmada\Vouchers\Features\Wallet\Models\VoucherWallet;
use AIArmada\Vouchers\Services\VoucherService;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class GuardWalletUser extends Model
{
    use HasUuids;
    use HasVoucherWallet;

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
    it('updates an exact voucher identifier and returns an immutable snapshot', function (): void {
        $voucher = guardVoucher();
        $other = guardVoucher();
        $otherName = $other->name;

        $updated = app(VoucherUpdater::class)->handle($voucher->id, ['name' => 'Exact update']);

        expect($updated)->toBeInstanceOf(VoucherData::class)
            ->and($updated->id)->toBe($voucher->id)
            ->and($updated->name)->toBe('Exact update')
            ->and($voucher->fresh()->name)->toBe('Exact update')
            ->and($other->fresh()->name)->toBe($otherName);
    });

    it('rejects invalid updates without partially saving valid fields', function (): void {
        $voucher = guardVoucher();
        $service = app(VoucherService::class);

        expect(fn () => $service->update($voucher->code, ['name' => 'Changed', 'value' => -100]))
            ->toThrow(ValidationException::class);

        expect($voucher->fresh()->name)->toBe('Guard Voucher')
            ->and($voucher->fresh()->value)->toBe(500);
    });

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
        expect(fn (): VoucherData => CreateVoucher::run(['code' => 'NOTYPE']))
            ->toThrow(ValidationException::class);

        expect(fn (): VoucherData => CreateVoucher::run(['code' => 'NOVALUE', 'type' => VoucherType::Fixed]))
            ->toThrow(ValidationException::class);
    });

    it('rejects unknown voucher types', function (): void {
        expect(fn (): VoucherData => CreateVoucher::run(['type' => 'mystery', 'value' => 100]))
            ->toThrow(ValidationException::class);
    });

    it('rejects negative values and oversized percentages', function (): void {
        expect(fn (): VoucherData => CreateVoucher::run(['type' => 'fixed', 'value' => -5]))
            ->toThrow(ValidationException::class);

        expect(fn (): VoucherData => CreateVoucher::run(['type' => 'percentage', 'value' => 10001]))
            ->toThrow(ValidationException::class);
    });

    it('accepts boundary values', function (): void {
        $zero = CreateVoucher::run(['code' => 'BOUND-ZERO', 'type' => 'fixed', 'value' => 0]);
        $fullPercent = CreateVoucher::run(['code' => 'BOUND-PCT', 'type' => 'percentage', 'value' => 10000]);

        expect($zero->value)->toBe(0)->and($fullPercent->value)->toBe(10000);
    });

    it('rejects bad currencies, limits, and date ranges', function (): void {
        expect(fn (): VoucherData => CreateVoucher::run(['type' => 'fixed', 'value' => 100, 'currency' => 'ZZZ']))
            ->toThrow(ValidationException::class);

        expect(fn (): VoucherData => CreateVoucher::run(['type' => 'fixed', 'value' => 100, 'currency' => 'TOOLONG']))
            ->toThrow(ValidationException::class);

        expect(fn (): VoucherData => CreateVoucher::run(['type' => 'fixed', 'value' => 100, 'usage_limit' => -1]))
            ->toThrow(ValidationException::class);

        expect(fn (): VoucherData => CreateVoucher::run(['type' => 'fixed', 'value' => 100, 'usage_limit_per_user' => -2]))
            ->toThrow(ValidationException::class);

        expect(fn (): VoucherData => CreateVoucher::run([
            'type' => 'fixed',
            'value' => 100,
            'starts_at' => now()->addDay(),
            'expires_at' => now(),
        ]))->toThrow(ValidationException::class);

        expect(fn (): VoucherData => CreateVoucher::run(['type' => 'fixed', 'value' => 100, 'starts_at' => 'not-a-date']))
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

        $first = AddVoucherToWallet::run($voucher->code, EloquentRecordReference::fromModel($user));
        $second = AddVoucherToWallet::run($voucher->code, EloquentRecordReference::fromModel($user));

        expect($second->id)->toBe($first->id)
            ->and($first->created)->toBeTrue()
            ->and($second->created)->toBeFalse()
            ->and(VoucherWallet::where('voucher_id', $voucher->id)->count())->toBe(1);
    });

    it('allows a fresh claim after redemption', function (): void {
        $voucher = guardVoucher();
        $user = guardWalletUser();

        $first = AddVoucherToWallet::run($voucher->code, EloquentRecordReference::fromModel($user));
        VoucherWallet::query()->findOrFail($first->id)->markAsRedeemed();

        $second = AddVoucherToWallet::run($voucher->code, EloquentRecordReference::fromModel($user));

        expect($second->id)->not->toBe($first->id)
            ->and(VoucherWallet::query()->findOrFail($second->id)->redeemed_at)->toBeNull();
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

        $claim = AddVoucherToWallet::run($voucher->code, EloquentRecordReference::fromModel($user));
        $entry = VoucherWallet::query()->findOrFail($claim->id);
        $stale = VoucherWallet::query()->whereKey($entry->id)->first();

        expect($stale->redeemed_at)->toBeNull();

        $stale->metadata = ['unpersisted' => true];
        $entry->markAsRedeemed();
        $winnerAt = (string) $entry->fresh()->redeemed_at;

        // A stale copy racing the winner syncs to the winner timestamp.
        $stale->markAsRedeemed();

        expect((string) $entry->fresh()->redeemed_at)->toBe($winnerAt)
            ->and((string) $stale->redeemed_at)->toBe($winnerAt)
            ->and($stale->fresh()->metadata)->toBeNull();
    });
});

describe('usage recording guards', function (): void {
    it('rejects a supplied voucher identity with a different code', function (): void {
        $first = guardVoucher();
        $second = guardVoucher();

        expect(fn () => RecordVoucherUsage::run($first->code, Money::MYR(100), voucherId: $second->id))
            ->toThrow(VoucherNotFoundException::class);

        expect(VoucherUsage::query()->whereIn('voucher_id', [$first->id, $second->id])->count())->toBe(0);
    });

    it('enforces a zero per-user usage limit', function (): void {
        $voucher = guardVoucher(['usage_limit_per_user' => 0]);
        $user = guardWalletUser();

        expect(fn () => RecordVoucherUsage::run($voucher->code, Money::MYR(100), redeemedBy: EloquentRecordReference::fromModel($user)))
            ->toThrow(VoucherUsageLimitException::class);
    });

    it('rejects usage in a different currency than the voucher', function (): void {
        $voucher = guardVoucher(['currency' => 'MYR']);

        expect(fn () => RecordVoucherUsage::run($voucher->code, Money::USD(100)))
            ->toThrow(ValidationException::class);

        expect(VoucherUsage::where('voucher_id', $voucher->id)->count())->toBe(0);
    });

    it('rejects an identified redemption without a redeemer key', function (): void {
        $voucher = guardVoucher(['usage_limit_per_user' => 1]);

        expect(fn () => app(VoucherService::class)->recordUsage($voucher->code, Money::MYR(100), redeemedBy: new GuardWalletUser))
            ->toThrow(InvalidArgumentException::class);

        expect(VoucherUsage::where('voucher_id', $voucher->id)->count())->toBe(0);
    });

    it('rejects an idempotency key reused across redemption channels', function (): void {
        $voucher = guardVoucher(['allows_manual_redemption' => true]);
        $metadata = ['idempotency_key' => 'shared-channel-key'];

        RecordVoucherUsage::run($voucher->code, Money::MYR(100), channel: 'web', metadata: $metadata);

        expect(fn () => RecordVoucherUsage::run(
            $voucher->code,
            Money::MYR(100),
            channel: (string) config('vouchers.redemption.manual_channel', 'manual'),
            metadata: $metadata,
        ))->toThrow(ValidationException::class);

        expect(VoucherUsage::where('voucher_id', $voucher->id)->count())->toBe(1);
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

it('rejects new usage for paused and future vouchers', function (): void {
    $paused = guardVoucher(['status' => 'paused']);
    $future = guardVoucher(['starts_at' => now()->addDay()]);

    foreach ([$paused, $future] as $voucher) {
        expect(fn () => RecordVoucherUsage::run($voucher->code, Money::MYR(100)))
            ->toThrow(InvalidVoucherException::class);
    }
});

it('rejects noncanonical voucher input fields', function (): void {
    foreach (['max_uses' => 1, 'minCartValue' => 100] as $field => $value) {
        expect(fn () => CreateVoucher::run(['type' => 'fixed', 'value' => 100, $field => $value]))
            ->toThrow(ValidationException::class);
    }
});

it('rejects empty or non-string explicit voucher codes on creation and update', function (): void {
    $voucher = guardVoucher();

    foreach (['', '   ', null, 123, ['invalid']] as $code) {
        expect(fn () => CreateVoucher::run(['code' => $code, 'type' => 'fixed', 'value' => 100]))
            ->toThrow(ValidationException::class)
            ->and(fn () => app(VoucherUpdater::class)->handle($voucher->id, ['code' => $code]))
            ->toThrow(ValidationException::class);
    }

    expect($voucher->fresh()->code)->toBe($voucher->code);
});
