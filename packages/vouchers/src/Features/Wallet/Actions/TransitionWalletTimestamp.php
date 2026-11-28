<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Features\Wallet\Api\WalletTimestampTransition;
use AIArmada\Vouchers\Models\VoucherWallet;
use Carbon\CarbonImmutable;

final readonly class TransitionWalletTimestamp implements WalletTimestampTransition
{
    public function handle(string $walletId, bool $redeemed): void
    {
        $column = $redeemed ? 'redeemed_at' : 'claimed_at';

        (new VoucherWallet)->getConnection()->transaction(function () use ($walletId, $column): void {
            $fresh = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(VoucherWallet::class, $walletId, lockForUpdate: true)
                : VoucherWallet::query()->whereKey($walletId)->lockForUpdate()->firstOrFail();

            if ($fresh->getAttribute($column) === null) {
                $fresh->setAttribute($column, CarbonImmutable::now());
                $fresh->saveOrFail();
            }
        });
    }

    /** @param list<string> $walletIds */
    public function handleMany(array $walletIds, bool $redeemed): void
    {
        if ($walletIds === []) {
            return;
        }

        $walletIds = array_values(array_unique($walletIds));
        sort($walletIds, SORT_STRING);

        (new VoucherWallet)->getConnection()->transaction(function () use ($walletIds, $redeemed): void {
            foreach ($walletIds as $walletId) {
                $this->handle($walletId, $redeemed);
            }
        });
    }
}
