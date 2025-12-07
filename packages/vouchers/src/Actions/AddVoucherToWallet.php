<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Add a voucher to the owner's wallet.
 */
final class AddVoucherToWallet
{
    use AsAction;

    /**
     * Add a voucher to the owner's wallet.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(string $code, Model $owner, ?array $metadata = null): VoucherWallet
    {
        return DB::transaction(function () use ($code, $owner, $metadata): VoucherWallet {
            $voucher = $this->findVoucher($code);

            // Check if already in wallet
            $existing = VoucherWallet::where('voucher_id', $voucher->id)
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->first();

            if ($existing) {
                return $existing;
            }

            return VoucherWallet::create([
                'voucher_id' => $voucher->id,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'metadata' => $metadata,
                'added_at' => now(),
            ]);
        });
    }

    private function findVoucher(string $code): VoucherModel
    {
        $normalizedCode = Str::upper(mb_trim($code));

        $voucher = VoucherModel::where('code', $normalizedCode)->first();

        if (! $voucher) {
            throw new VoucherNotFoundException("Voucher with code '{$code}' not found.");
        }

        return $voucher;
    }
}
