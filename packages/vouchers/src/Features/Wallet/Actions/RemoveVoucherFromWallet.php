<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Actions;

use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Features\Wallet\Api\WalletRemoval;
use AIArmada\Vouchers\Features\Wallet\Models\VoucherWallet;
use InvalidArgumentException;

final readonly class RemoveVoucherFromWallet implements WalletRemoval
{
    public function __construct(private VoucherCatalog $catalog) {}

    public function handle(string $code, string $holderType, string $holderId): bool
    {
        if ($holderType === '' || $holderId === '') {
            throw new InvalidArgumentException('A persisted wallet holder is required.');
        }

        $id = $this->catalog->resolveId($code);

        if ($id === null) {
            throw VoucherNotFoundException::withCode($code);
        }

        return (new VoucherWallet)->getConnection()->transaction(function () use ($id, $holderType, $holderId): bool {
            $entry = VoucherWallet::query()
                ->forOwner(includeGlobal: false)
                ->where('voucher_id', $id)
                ->where('holder_type', $holderType)
                ->where('holder_id', $holderId)
                ->whereNull('redeemed_at')
                ->lockForUpdate()
                ->first();

            return $entry instanceof VoucherWallet && $entry->delete() === true;
        });
    }
}
