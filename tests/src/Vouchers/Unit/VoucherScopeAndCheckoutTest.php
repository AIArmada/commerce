<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Vouchers\Actions\ApplyVoucherToCart;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Listeners\ValidateVoucherOnCheckout;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Expired;
use AIArmada\Vouchers\Support\VoucherCartMetadata;
use AIArmada\Vouchers\Traits\HasVouchers;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ScopeWalletUser extends Model
{
    use HasUuids;
    use HasVouchers;

    protected $table = 'users';

    protected $guarded = [];
}

function scopeWalletUser(string $prefix): ScopeWalletUser
{
    return ScopeWalletUser::create([
        'name' => 'Scope User',
        'email' => $prefix . '-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);
}

function scopeVoucher(array $attributes = []): Voucher
{
    return Voucher::create(array_merge([
        'code' => 'SCOPE-' . mb_strtoupper(uniqid()),
        'name' => 'Scope Voucher',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'status' => Active::class,
    ], $attributes));
}

describe('live voucher scope', function (): void {
    it('aggregates usage with a single join instead of per-row subqueries', function (): void {
        $live = scopeVoucher(['usage_limit' => 5]);
        $atLimit = scopeVoucher(['usage_limit' => 1]);

        VoucherUsage::create([
            'voucher_id' => $atLimit->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'channel' => 'web',
            'used_at' => now(),
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $ids = Voucher::query()->live()->pluck('id')->all();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($ids)->toContain($live->id)->not->toContain($atLimit->id)
            ->and($queries)->toHaveCount(1)
            ->and(mb_strtolower($queries[0]['query']))->toContain('voucher_usage_counts');
    });
});

describe('wallet helper queries', function (): void {
    it('filters available and expired wallets in the database', function (): void {
        $user = scopeWalletUser('wallet-filter');

        $active = scopeVoucher();
        $expired = scopeVoucher(['expires_at' => now()->subDay()]);
        $paused = scopeVoucher(['status' => 'paused']);

        $user->addVoucherToWallet($active->code);
        $user->addVoucherToWallet($expired->code);
        $user->addVoucherToWallet($paused->code);

        $available = $user->getAvailableVouchers();
        $expiredWallets = $user->getExpiredVouchers();

        expect($available->pluck('voucher_id')->all())->toBe([$active->id])
            ->and($expiredWallets->pluck('voucher_id')->all())->toBe([$expired->id]);
    });

    it('preloads usage counts with wallet vouchers', function (): void {
        $user = scopeWalletUser('wallet-count');

        $voucher = scopeVoucher();
        $user->addVoucherToWallet($voucher->code);

        $available = $user->getAvailableVouchers();

        expect($available)->toHaveCount(1)
            ->and(array_key_exists('usages_count', $available->first()->voucher->getAttributes()))->toBeTrue();
    });

    it('honors the wallet listing limit', function (): void {
        $user = scopeWalletUser('wallet-limit');

        $user->addVoucherToWallet(scopeVoucher()->code);
        $user->addVoucherToWallet(scopeVoucher()->code);

        expect($user->getAvailableVouchers(1))->toHaveCount(1)
            ->and($user->getAvailableVouchers())->toHaveCount(2);
    });
});

describe('checkout voucher validation', function (): void {
    it('removes stale discount conditions for invalid codes', function (): void {
        $voucher = scopeVoucher();

        $cart = new Cart(new InMemoryStorage, 'checkout-stale-' . uniqid());
        $cart->setMetadata(VoucherCartMetadata::VOUCHER_CODES, [$voucher->code]);

        ApplyVoucherToCart::run($cart, $voucher->code);

        expect($cart->getDynamicConditions())->not->toBeEmpty();

        $voucher->update(['expires_at' => now()->subMinute()]);

        $event = new class($cart)
        {
            public function __construct(public readonly Cart $cart) {}
        };

        app(ValidateVoucherOnCheckout::class)->handle($event);

        expect($cart->getMetadata(VoucherCartMetadata::VOUCHER_CODES))->toBe([])
            ->and($cart->getDynamicConditions()->isEmpty())->toBeTrue();
    });
});

describe('voucher expiry command', function (): void {

    it('expires past-due vouchers', function (): void {
        $pastDue = scopeVoucher(['expires_at' => now()->subMinute()]);
        $upcoming = scopeVoucher(['expires_at' => now()->addDay()]);

        $exitCode = $this->artisan('vouchers:expire')->run();

        expect($exitCode)->toBe(0);

        expect($pastDue->fresh()->status)->toBeInstanceOf(Expired::class)
            ->and($upcoming->fresh()->status)->toBeInstanceOf(Active::class);
    });

    it('reports without changing in dry-run mode', function (): void {
        $pastDue = scopeVoucher(['expires_at' => now()->subMinute()]);

        $exitCode = $this->artisan('vouchers:expire', ['--dry-run' => true])->run();

        expect($exitCode)->toBe(0);

        expect($pastDue->fresh()->status)->toBeInstanceOf(Active::class);
    });

    it('expires vouchers across owners', function (): void {
        config()->set('vouchers.owner.enabled', true);
        config()->set('vouchers.owner.include_global', false);

        $ownerA = User::query()->create(['name' => 'Expire A', 'email' => 'expire-a@example.com', 'password' => 'secret']);
        $ownerB = User::query()->create(['name' => 'Expire B', 'email' => 'expire-b@example.com', 'password' => 'secret']);

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

        $voucherA = OwnerContext::withOwner($ownerA, static fn (): Voucher => scopeVoucher(['expires_at' => now()->subMinute()]));
        $voucherB = OwnerContext::withOwner($ownerB, static fn (): Voucher => scopeVoucher(['expires_at' => now()->subMinute()]));

        $exitCode = $this->artisan('vouchers:expire')->run();

        expect($exitCode)->toBe(0);

        expect($voucherA->fresh()->status)->toBeInstanceOf(Expired::class)
            ->and($voucherB->fresh()->status)->toBeInstanceOf(Expired::class);
    });
});

describe('voucher code uniqueness', function (): void {
    it('allows two owners to reuse the same voucher code', function (): void {
        config()->set('vouchers.owner.enabled', true);
        config()->set('vouchers.owner.include_global', false);

        // Owner scoping snapshots at model boot; reboot models so the
        // enabled flag takes effect, then restore boot state afterwards.
        Voucher::clearBootedModels();

        try {
            $ownerA = scopeWalletUser('voucher-code-a');
            $ownerB = scopeWalletUser('voucher-code-b');

            $voucherA = OwnerContext::withOwner($ownerA, static fn (): Voucher => scopeVoucher(['code' => 'SHARED10']));
            $voucherB = OwnerContext::withOwner($ownerB, static fn (): Voucher => scopeVoucher(['code' => 'SHARED10']));

            expect($voucherA->owner_id)->not->toBeNull()
                ->and($voucherB->owner_id)->not->toBeNull()
                ->and((string) $voucherA->owner_id)->not->toBe((string) $voucherB->owner_id)
                ->and($voucherA->code)->toBe('SHARED10')
                ->and($voucherB->code)->toBe('SHARED10');
        } finally {
            Voucher::clearBootedModels();
        }
    });

    it('rejects a duplicate voucher code within the same owner', function (): void {
        config()->set('vouchers.owner.enabled', true);
        config()->set('vouchers.owner.include_global', false);

        Voucher::clearBootedModels();

        try {
            $owner = scopeWalletUser('voucher-code-same');

            OwnerContext::withOwner($owner, static fn (): Voucher => scopeVoucher(['code' => 'ONCEONLY']));

            expect(fn () => OwnerContext::withOwner($owner, static fn (): Voucher => scopeVoucher(['code' => 'ONCEONLY'])))
                ->toThrow(QueryException::class);
        } finally {
            Voucher::clearBootedModels();
        }
    });
});
