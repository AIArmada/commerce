<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Queries;

use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherWalletReader;
use AIArmada\Vouchers\Features\Wallet\Api\WalletStatistics;
use AIArmada\Vouchers\Features\Wallet\Models\VoucherWallet;

final class ReadWalletStatistics implements WalletStatistics
{
    public function __construct(private readonly VoucherWalletReader $reader) {}

    /** @return array{total: int, claimed: int, redeemed: int, available: int} */
    public function forVoucher(string $voucherId): array
    {
        if ($this->reader->readMany([$voucherId]) === []) {
            return ['total' => 0, 'claimed' => 0, 'redeemed' => 0, 'available' => 0];
        }

        $counts = VoucherWallet::query()
            ->where('voucher_id', $voucherId)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(claimed_at) as claimed')
            ->selectRaw('COUNT(redeemed_at) as redeemed')
            ->selectRaw('COUNT(CASE WHEN claimed_at IS NOT NULL AND redeemed_at IS NULL THEN 1 END) as available')
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'claimed' => (int) ($counts->claimed ?? 0),
            'redeemed' => (int) ($counts->redeemed ?? 0),
            'available' => (int) ($counts->available ?? 0),
        ];
    }
}
