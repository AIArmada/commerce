<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher;
use AIArmada\Vouchers\Features\Lifecycle\Models\VoucherUsage;
use AIArmada\Vouchers\Features\Wallet\Models\VoucherWallet;
use AIArmada\Vouchers\Services\VoucherService;
use Akaunting\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('cascades voucher deletion to usage and wallet rows with foreign keys enforced', function (bool $throughService): void {
    $customer = User::factory()->create();
    $originalConnection = DB::getDefaultConnection();
    $connection = 'voucher-cascade';

    config([
        'database.connections.' . $connection => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'vouchers.owner.enabled' => false,
        'vouchers.wallet.enabled' => true,
    ]);

    DB::setDefaultConnection($connection);
    Schema::clearResolvedInstance('db.schema');

    try {
        expect((int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys)->toBe(1);

        $migrationDirectory = dirname(__DIR__, 4) . '/packages/vouchers/database/migrations';

        foreach (glob($migrationDirectory . '/*.php') as $file) {
            (require $file)->up();
        }

        $voucher = Voucher::create([
            'code' => 'CASCADEDELETE',
            'name' => 'Cascade deletion',
            'type' => 'fixed',
            'value' => 100,
            'currency' => 'MYR',
            'status' => 'active',
        ]);

        $service = app(VoucherService::class);
        $service->recordUsage($voucher->code, Money::MYR(100), redeemedBy: $customer);
        $wallet = $service->addToWallet($voucher->code, $customer);
        $usage = $voucher->usages()->sole();

        expect($throughService ? $service->delete($voucher->code) : $voucher->delete())->toBeTrue()
            ->and(VoucherUsage::query()->whereKey($usage->getKey())->exists())->toBeFalse()
            ->and(VoucherWallet::query()->whereKey($wallet->id)->exists())->toBeFalse();
    } finally {
        DB::setDefaultConnection($originalConnection);
        Schema::clearResolvedInstance('db.schema');
        DB::purge($connection);
    }
})->with([
    'model deletion' => false,
    'service deletion' => true,
]);
