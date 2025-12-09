<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new voucher.
 */
final class CreateVoucher
{
    use AsAction;

    /**
     * Create a new voucher with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): VoucherModel
    {
        return DB::transaction(function () use ($data): VoucherModel {
            $code = $data['code'] ?? $this->generateCode();

            $voucher = VoucherModel::create([
                'code' => $this->normalizeCode($code),
                'type' => $data['type'],
                'value' => $data['value'],
                'currency' => $data['currency'] ?? config('vouchers.currency', 'MYR'),
                'description' => $data['description'] ?? null,
                'max_uses' => $data['max_uses'] ?? null,
                'max_uses_per_user' => $data['max_uses_per_user'] ?? null,
                'min_order_value' => $data['min_order_value'] ?? null,
                'max_discount_value' => $data['max_discount_value'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'starts_at' => $data['starts_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'owner_type' => $data['owner_type'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
            ]);

            return $voucher;
        });
    }

    private function generateCode(): string
    {
        $prefix = config('vouchers.code_prefix', '');
        $length = config('vouchers.code_length', 8);

        do {
            $code = $prefix . Str::upper(Str::random($length));
        } while (VoucherModel::where('code', $code)->exists());

        return $code;
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(mb_trim($code));
    }
}
