<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet;

use AIArmada\Vouchers\Features\Wallet\Actions\AddVoucherToWallet;
use AIArmada\Vouchers\Features\Wallet\Actions\RemoveVoucherFromWallet;
use AIArmada\Vouchers\Features\Wallet\Actions\TransitionWalletTimestamp;
use AIArmada\Vouchers\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Vouchers\Runtime\WalletClaim;
use AIArmada\Vouchers\Runtime\WalletEntryDeletion;
use AIArmada\Vouchers\Features\Wallet\Actions\DeleteWalletEntry;
use AIArmada\Vouchers\Runtime\WalletCleanup;
use AIArmada\Vouchers\Features\Wallet\Actions\DeleteVoucherWalletEntries;
use AIArmada\Vouchers\Runtime\WalletRemoval;
use AIArmada\Vouchers\Runtime\WalletTimestampTransition;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(WalletClaim::class, AddVoucherToWallet::class);
        $app->bind(WalletEntryDeletion::class, DeleteWalletEntry::class);
        $app->bind(WalletCleanup::class, DeleteVoucherWalletEntries::class);
        $app->bind(WalletRemoval::class, RemoveVoucherFromWallet::class);
        $app->bind(WalletTimestampTransition::class, TransitionWalletTimestamp::class);
    }

    public function boot(Application $app): void {}
}
