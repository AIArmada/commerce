<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\EntryPoints\Traits;

use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherWalletReader;
use AIArmada\Vouchers\Features\Wallet\Api\WalletClaim;
use AIArmada\Vouchers\Features\Wallet\Api\WalletRemoval;
use AIArmada\Vouchers\Features\Wallet\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * @mixin Model
 */
trait HasVoucherWallet
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
     * Add a voucher to the wallet (claimed automatically) - Coupon System.
     */
    public function addVoucherToWallet(string $voucherCode): VoucherWallet
    {
        $holder = EloquentRecordReference::fromModel($this);
        $claim = app(WalletClaim::class)->handle($voucherCode, $holder);

        return $this->voucherWallets()->whereKey($claim->id)->firstOrFail();
    }

    /**
     * Remove a voucher from the wallet - Coupon System.
     */
    public function removeVoucherFromWallet(string $voucherCode): bool
    {
        return app(WalletRemoval::class)->handle(
            $voucherCode,
            $this->getMorphClass(),
            $this->exists ? (string) $this->getKey() : '',
        );
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
        return $this->readWalletEntries(expired: false, limit: $limit);
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
        return $this->readWalletEntries(expired: true, limit: $limit);
    }

    /** @return Collection<int, VoucherWallet> */
    private function readWalletEntries(bool $expired, ?int $limit): Collection
    {
        $limit = $limit === null ? null : max(1, $limit);
        $reader = app(VoucherWalletReader::class);
        /** @var Collection<int, VoucherWallet> $matches */
        $matches = collect();

        $this->voucherWallets()
            ->whereNotNull('claimed_at')
            ->whereNull('redeemed_at')
            ->chunkById(100, static function (Collection $wallets) use ($reader, $expired, $limit, $matches): bool {
                $states = $reader->readMany($wallets->pluck('voucher_id')->unique()->values()->all());

                foreach ($wallets as $wallet) {
                    $state = $states[$wallet->voucher_id] ?? null;

                    if ($state !== null && ($expired ? $state->expired : $state->redeemable)) {
                        $matches->push($wallet);

                        if ($limit !== null && $matches->count() >= $limit) {
                            return false;
                        }
                    }
                }

                return true;
            });

        return $matches;
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
