<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet;

use AIArmada\Vouchers\Features\Wallet\Actions\AddVoucherToWallet;
use AIArmada\Vouchers\Features\Wallet\Actions\DeleteWalletEntry;
use AIArmada\Vouchers\Features\Wallet\Actions\RemoveVoucherFromWallet;
use AIArmada\Vouchers\Features\Wallet\Actions\TransitionWalletTimestamp;
use AIArmada\Vouchers\Features\Wallet\Api\WalletClaim;
use AIArmada\Vouchers\Features\Wallet\Api\WalletEntryDeletion;
use AIArmada\Vouchers\Features\Wallet\Api\WalletRemoval;
use AIArmada\Vouchers\Features\Wallet\Api\WalletStatistics;
use AIArmada\Vouchers\Features\Wallet\Api\WalletTimestampTransition;
use AIArmada\Vouchers\Features\Wallet\Queries\ReadWalletStatistics;
use AIArmada\Vouchers\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(WalletClaim::class, AddVoucherToWallet::class);
        $app->bind(WalletEntryDeletion::class, DeleteWalletEntry::class);
        $app->bind(WalletRemoval::class, RemoveVoucherFromWallet::class);
        $app->bind(WalletStatistics::class, ReadWalletStatistics::class);
        $app->bind(WalletTimestampTransition::class, TransitionWalletTimestamp::class);
    }

    public function boot(Application $app): void {}
}
