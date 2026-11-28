<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Features\CartIntegration\Actions\ApplyVoucherToCart;
use AIArmada\Vouchers\Features\CartIntegration\EntryPoints\Listeners\ValidateVoucherOnCheckout;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Runtime\CartVoucherRemoval;
use AIArmada\Vouchers\Runtime\WalletClaim;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Expired;
use AIArmada\Vouchers\Support\VoucherCartMetadata;
use AIArmada\Vouchers\Traits\HasVouchers;
use Akaunting\Money\Money;
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
        'status' => 'active',
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
        ApplyVoucherToCart::run($cart, $voucher->code);
        $cart->setMetadata(VoucherCartMetadata::VOUCHER_CODES, [$voucher->code]);

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

it('revalidates directly registered vouchers without voucher-code metadata', function (): void {
    $voucher = scopeVoucher();
    $cart = new Cart(new InMemoryStorage, 'checkout-direct-' . uniqid());

    ApplyVoucherToCart::run($cart, $voucher->code);
    expect($cart->getDynamicConditions())->not->toBeEmpty();

    $voucher->update(['expires_at' => now()->subMinute()]);
    $event = new class($cart)
    {
        public function __construct(public readonly Cart $cart) {}
    };

    app(ValidateVoucherOnCheckout::class)->handle($event);

    expect($cart->getDynamicConditions()->isEmpty())->toBeTrue()
        ->and($cart->getStoredConditions()->isEmpty())->toBeTrue();
});

describe('voucher expiry is derived from the date', function (): void {

    it('reports a past-due voucher as expired without any sweep', function (): void {
        $pastDue = scopeVoucher(['expires_at' => now()->subMinute()]);
        $upcoming = scopeVoucher(['expires_at' => now()->addDay()]);

        // The stored status is untouched …
        expect($pastDue->fresh()->status)->toBeInstanceOf(Active::class)
            ->and($pastDue->isExpired())->toBeTrue()
            // … but every reader sees Expired, and redemption already refused it.
            ->and($pastDue->fresh()->effective_status)->toBeInstanceOf(Expired::class)
            // Filament reads column state via data_get, so the badge needs the
            // attribute to resolve that way and not only as a direct read.
            ->and(data_get($pastDue->fresh(), 'effective_status'))->toBeInstanceOf(Expired::class)
            ->and($pastDue->fresh()->isActive())->toBeTrue()
            ->and($pastDue->fresh()->canBeRedeemed())->toBeFalse()
            // A live voucher is unaffected.
            ->and($upcoming->fresh()->isExpired())->toBeFalse()
            ->and($upcoming->fresh()->effective_status)->toBeInstanceOf(Active::class)
            ->and($upcoming->fresh()->canBeRedeemed())->toBeTrue();
    });

    it('keeps a depleted past-due voucher depleted', function (): void {
        $depleted = scopeVoucher(['expires_at' => now()->subMinute()]);
        $depleted->status = new Depleted($depleted);
        $depleted->save();

        expect($depleted->fresh()->effective_status)->toBeInstanceOf(Depleted::class);
    });

    it('keeps a stored-expired voucher expired', function (): void {
        $stored = scopeVoucher(['expires_at' => now()->addDay()]);
        $stored->status = new Expired($stored);
        $stored->save();

        expect($stored->fresh()->isExpired())->toBeFalse()
            ->and($stored->fresh()->effective_status)->toBeInstanceOf(Expired::class);
    });

    it('excludes past-due vouchers from the live scope', function (): void {
        $pastDue = scopeVoucher(['expires_at' => now()->subMinute()]);
        $live = scopeVoucher(['expires_at' => now()->addDay()]);

        $liveIds = Voucher::query()->live()->pluck('id');

        expect($liveIds)->toContain($live->id)
            ->and($liveIds)->not->toContain($pastDue->id);
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

it('removes a voucher from metadata and all cart condition stores together', function (): void {
    $voucher = scopeVoucher();
    $cart = new Cart(new InMemoryStorage, 'checkout-remove-' . uniqid());

    ApplyVoucherToCart::run($cart, $voucher->code);
    $cart->setMetadata(VoucherCartMetadata::VOUCHER_CODES, [$voucher->code]);

    app(CartVoucherRemoval::class)->handle($cart, $voucher->code);

    expect($cart->getMetadata(VoucherCartMetadata::VOUCHER_CODES))->toBe([])
        ->and($cart->getDynamicConditions()->isEmpty())->toBeTrue()
        ->and($cart->getStoredConditions()->isEmpty())->toBeTrue();
});

it('prefers the exact owner voucher when a global voucher shares its code', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', true);
    Voucher::clearBootedModels();

    try {
        $owner = scopeWalletUser('voucher-owner-priority');
        $global = OwnerContext::withOwner(null, static fn (): Voucher => scopeVoucher(['code' => 'OWNERFIRST', 'value' => 100]));
        $owned = OwnerContext::withOwner($owner, static fn (): Voucher => scopeVoucher(['code' => 'OWNERFIRST', 'value' => 500]));

        OwnerContext::withOwner($owner, function () use ($owned): void {
            $service = app(VoucherService::class);

            expect($service->find('OWNERFIRST')?->id)->toBe($owned->id);

            $service->recordUsage('OWNERFIRST', Money::MYR(500));
        });

        expect($owned->usages()->count())->toBe(1)
            ->and($global->usages()->count())->toBe(0);
    } finally {
        Voucher::clearBootedModels();
    }
});

it('removes directly registered discounts when the voucher value changes before checkout', function (): void {
    $voucher = scopeVoucher(['value' => 500]);
    $cart = new Cart(new InMemoryStorage, 'checkout-changed-value-' . uniqid());

    ApplyVoucherToCart::run($cart, $voucher->code);
    $cart->setMetadata(VoucherCartMetadata::VOUCHER_CODES, [$voucher->code]);

    $voucher->update(['value' => 100]);

    $event = new class($cart)
    {
        public function __construct(public readonly Cart $cart) {}
    };

    app(ValidateVoucherOnCheckout::class)->handle($event);

    expect($cart->getMetadata(VoucherCartMetadata::VOUCHER_CODES))->toBe([])
        ->and($cart->getDynamicConditions()->isEmpty())->toBeTrue()
        ->and($cart->getStoredConditions()->isEmpty())->toBeTrue();
});

it('keeps an unchanged directly registered voucher at checkout', function (): void {
    $voucher = scopeVoucher();
    $cart = new Cart(new InMemoryStorage, 'checkout-unchanged-value-' . uniqid());

    ApplyVoucherToCart::run($cart, $voucher->code);

    $event = new class($cart)
    {
        public function __construct(public readonly Cart $cart) {}
    };

    app(ValidateVoucherOnCheckout::class)->handle($event);

    expect($cart->getDynamicConditions())->not->toBeEmpty();
});

it('rejects unresolved owners before global voucher lookups or wallet claims', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    $holder = scopeWalletUser('unresolved-owner');
    $global = OwnerContext::withOwner(null, static fn (): Voucher => scopeVoucher(['code' => 'EXPLICIT-GLOBAL']));
    $service = app(VoucherService::class);

    OwnerContext::withOwner(null, function () use ($service, $global): void {
        expect($service->find('EXPLICIT-GLOBAL')?->id)->toBe($global->id);
    });

    expect(fn () => $service->find('EXPLICIT-GLOBAL'))->toThrow(NoCurrentOwnerException::class)
        ->and(fn () => $service->addToWallet('EXPLICIT-GLOBAL', $holder))->toThrow(NoCurrentOwnerException::class)
        ->and(fn () => $holder->hasVoucherInWallet('EXPLICIT-GLOBAL'))->toThrow(NoCurrentOwnerException::class)
        ->and(fn () => Voucher::query()->forOwner()->exists())->toThrow(NoCurrentOwnerException::class);
});

it('resolves wallet code queries to the tenant voucher ahead of a matching global voucher', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', true);

    $owner = scopeWalletUser('wallet-code-priority');
    $global = OwnerContext::withOwner(null, static fn (): Voucher => scopeVoucher(['code' => 'WALLET-PRIORITY']));
    $owned = OwnerContext::withOwner($owner, static fn (): Voucher => scopeVoucher(['code' => 'WALLET-PRIORITY']));

    OwnerContext::withOwner($owner, function () use ($owner, $global, $owned): void {
        app(WalletClaim::class)->handle($global, $owner);
        expect($owner->hasVoucherInWallet('WALLET-PRIORITY'))->toBeFalse();

        $owner->addVoucherToWallet('WALLET-PRIORITY');
        expect($owner->hasVoucherInWallet('WALLET-PRIORITY'))->toBeTrue();

        $owner->markVoucherAsRedeemed('WALLET-PRIORITY');
        expect($owner->voucherWallets()->where('voucher_id', $owned->id)->sole()->redeemed_at)->not->toBeNull()
            ->and($owner->voucherWallets()->where('voucher_id', $global->id)->sole()->redeemed_at)->toBeNull();
    });
});
