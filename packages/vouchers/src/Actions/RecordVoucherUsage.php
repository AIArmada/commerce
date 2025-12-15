<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherUsage;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Record usage of a voucher.
 */
final class RecordVoucherUsage
{
    use AsAction;

    /**
     * Record a voucher usage.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null,
        ?VoucherModel $voucherModel = null
    ): VoucherUsage {
        return DB::transaction(function () use ($code, $discountAmount, $channel, $metadata, $redeemedBy, $notes, $voucherModel): VoucherUsage {
            $voucher = $voucherModel ?? $this->findVoucher($code);

            $usage = VoucherUsage::create([
                'voucher_id' => $voucher->id,
                'discount_amount' => $discountAmount->getAmount(),
                'currency' => $discountAmount->getCurrency()->getCurrency(),
                'channel' => $channel ?? 'web',
                'metadata' => $metadata,
                'redeemed_by_type' => $redeemedBy?->getMorphClass(),
                'redeemed_by_id' => $redeemedBy?->getKey(),
                'notes' => $notes,
                'used_at' => now(),
            ]);

            // Update the voucher use count
            $voucher->increment('applied_count');

            return $usage;
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
