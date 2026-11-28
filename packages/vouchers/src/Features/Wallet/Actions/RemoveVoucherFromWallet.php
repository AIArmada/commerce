<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Models\VoucherWallet;
use AIArmada\Vouchers\Runtime\WalletRemoval;
use Illuminate\Database\Eloquent\Model;

final readonly class RemoveVoucherFromWallet implements WalletRemoval
{
    public function __construct(private VoucherCatalog $catalog) {}

    public function handle(string $code, Model $holder): bool
    {
        $id = $this->catalog->resolveId($code);

        if ($id === null) {
            throw VoucherNotFoundException::withCode($code);
        }

        return (new VoucherWallet)->getConnection()->transaction(function () use ($id, $holder): bool {
            $entry = VoucherWallet::query()
                ->forOwner(includeGlobal: false)
                ->where('voucher_id', $id)
                ->where('holder_type', $holder->getMorphClass())
                ->where('holder_id', $holder->getKey())
                ->whereNull('redeemed_at')
                ->lockForUpdate()
                ->first();

            return $entry instanceof VoucherWallet && $entry->delete() === true;
        });
    }
}
