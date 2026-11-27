<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet;

use AIArmada\Vouchers\Features\Wallet\Actions\AddVoucherToWallet;
use AIArmada\Vouchers\Features\Wallet\Actions\RemoveVoucherFromWallet;
use AIArmada\Vouchers\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Vouchers\Runtime\WalletClaim;
use AIArmada\Vouchers\Runtime\WalletRemoval;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(WalletClaim::class, AddVoucherToWallet::class);
        $app->bind(WalletRemoval::class, RemoveVoucherFromWallet::class);
    }

    public function boot(Application $app): void {}
}
