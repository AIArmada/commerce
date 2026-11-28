<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Runtime\WalletTimestampTransition;
use Carbon\CarbonImmutable;

final readonly class TransitionWalletTimestamp implements WalletTimestampTransition
{
    public function handle(VoucherWallet $wallet, bool $redeemed): void
    {
        if (! $wallet->exists || $wallet->getKey() === null) {
            return;
        }

        $column = $redeemed ? 'redeemed_at' : 'claimed_at';

        $wallet->getConnection()->transaction(function () use ($wallet, $column): void {
            $fresh = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(VoucherWallet::class, $wallet->getKey(), lockForUpdate: true)
                : $wallet->newQuery()->whereKey($wallet->getKey())->lockForUpdate()->first();

            if (! $fresh instanceof VoucherWallet) {
                return;
            }

            if ($fresh->getAttribute($column) === null) {
                $fresh->setAttribute($column, CarbonImmutable::now());
                $fresh->saveOrFail();
            }

            $wallet->setRawAttributes($fresh->getAttributes(), true);
        });
    }
}
