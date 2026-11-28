<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Traits;

use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Runtime\WalletClaim;
use AIArmada\Vouchers\Runtime\WalletRemoval;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * @mixin Model
 */
trait HasVouchers
{
    /**
     * Get all vouchers in the wallet (Coupon System).
     *
     * @return MorphMany<VoucherWallet, $this>
     */
    public function voucherWallets(): MorphMany
    {
        $relation = $this->morphMany(VoucherWallet::class, 'holder');
        $relation->getQuery()->forOwner(includeGlobal: false);

        return $relation;
    }

    /**
     * Get the voucher usages redeemed by this model.
     *
     * @return MorphMany<VoucherUsage, $this>
     */
    public function voucherUsages(): MorphMany
    {
        return $this->morphMany(VoucherUsage::class, 'redeemedBy');
    }

    /**
     * Add a voucher to the wallet (claimed automatically) - Coupon System.
     */
    public function addVoucherToWallet(string $voucherCode): VoucherWallet
    {
        return app(WalletClaim::class)->handle($voucherCode, $this);
    }

    /**
     * Remove a voucher from the wallet - Coupon System.
     */
    public function removeVoucherFromWallet(string $voucherCode): bool
    {
        return app(WalletRemoval::class)->handle($voucherCode, $this);
    }

    /**
     * Check if voucher exists in wallet - Coupon System.
     */
    public function hasVoucherInWallet(string $voucherCode): bool
    {
        $voucherId = app(VoucherCatalog::class)->resolveId($voucherCode);

        if ($voucherId === null) {
            return false;
        }

        return $this->voucherWallets()
            ->where('voucher_id', $voucherId)
            ->exists();
    }

    /**
     * Get all available (usable) vouchers from wallet - Coupon System.
     *
     * @return Collection<int, VoucherWallet>
     */
    public function getAvailableVouchers(?int $limit = null): Collection
    {
        /** @var Collection<int, VoucherWallet> $wallets */
        $wallets = $this->voucherWallets()
            ->with(['voucher' => static function (BelongsTo $query): void {
                $query->withCount('usages');
            }])
            ->whereNotNull('claimed_at')
            ->whereNull('redeemed_at')
            ->whereHas('voucher', static function (Builder $query): void {
                $query->live();
            })
            ->when($limit !== null, static function (Builder $query) use ($limit): void {
                $query->limit(max(1, $limit));
            })
            ->get();

        return $wallets->filter(fn (VoucherWallet $wallet) => $wallet->canBeUsed());
    }

    /**
     * Get all redeemed vouchers from wallet - Coupon System.
     *
     * @return Collection<int, VoucherWallet>
     */
    public function getRedeemedVouchers(): Collection
    {
        /** @var Collection<int, VoucherWallet> $wallets */
        $wallets = $this->voucherWallets()
            ->with('voucher')
            ->whereNotNull('redeemed_at')
            ->orderByDesc('redeemed_at')
            ->get();

        return $wallets;
    }

    /**
     * Get expired vouchers from wallet - Coupon System.
     *
     * @return Collection<int, VoucherWallet>
     */
    public function getExpiredVouchers(?int $limit = null): Collection
    {
        /** @var Collection<int, VoucherWallet> $wallets */
        $wallets = $this->voucherWallets()
            ->with(['voucher' => static function (BelongsTo $query): void {
                $query->withCount('usages');
            }])
            ->whereNotNull('claimed_at')
            ->whereNull('redeemed_at')
            ->whereHas('voucher', static function (Builder $query): void {
                $query->whereNotNull('expires_at')->where('expires_at', '<', CarbonImmutable::now());
            })
            ->when($limit !== null, static function (Builder $query) use ($limit): void {
                $query->limit(max(1, $limit));
            })
            ->get();

        return $wallets->filter(fn (VoucherWallet $wallet) => $wallet->isExpired());
    }

    /**
     * Mark a wallet voucher as redeemed - Coupon System.
     */
    public function markVoucherAsRedeemed(string $voucherCode): void
    {
        $voucherId = app(VoucherCatalog::class)->resolveId($voucherCode);

        if ($voucherId === null) {
            throw VoucherNotFoundException::withCode($voucherCode);
        }

        /** @var VoucherWallet|null $walletEntry */
        $walletEntry = $this->voucherWallets()
            ->where('voucher_id', $voucherId)
            ->whereNull('redeemed_at')
            ->first();

        if ($walletEntry) {
            $walletEntry->markAsRedeemed();
        }
    }
}
