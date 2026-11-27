<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\States\VoucherStatus;
use AIArmada\Vouchers\Support\VoucherAffiliateOwnershipGuard;
use AIArmada\Vouchers\Support\VoucherInputValidator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateVoucher
{
    use AsAction;
    use NormalizesVoucherCodes;

    public function __construct(private readonly VoucherInputValidator $validator) {}

    /** @param array<string, mixed> $data */
    public function handle(string | VoucherModel $voucher, array $data): VoucherModel
    {
        $record = $voucher instanceof VoucherModel
            ? $voucher
            : VoucherModel::query()->where('code', $this->normalizeCode($voucher))->firstOrFail();

        return $record->getConnection()->transaction(function () use ($record, $data): VoucherModel {
            if (config('vouchers.owner.enabled', false)) {
                $record = OwnerWriteGuard::findOrFailForOwner(VoucherModel::class, $record->getKey());
            }

            $locked = VoucherModel::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (isset($data['code'])) {
                $data['code'] = $this->normalizeCode((string) $data['code']);
            }

            $data = VoucherAffiliateOwnershipGuard::sanitize($data);
            $status = $data['status'] ?? null;
            unset(
                $data['status'],
                $data['owner_type'],
                $data['owner_id'],
                $data['applied_count'],
                $data['paused_at'],
                $data['depleted_at'],
                $data['last_activated_at'],
            );

            $this->validator->validate(array_replace($locked->getAttributes(), $data));

            if (isset($data['currency']) && is_string($data['currency'])) {
                $data['currency'] = mb_strtoupper($data['currency']);
            }

            $locked->update($data);

            if ($status instanceof VoucherStatus || is_string($status)) {
                $normalized = VoucherStatus::normalize($status);
                $target = VoucherStatus::fromString($status, $locked);

                if ($target->getValue() !== $normalized) {
                    throw ValidationException::withMessages(['status' => 'Invalid voucher status.']);
                }

                if ($normalized !== $locked->status->getValue()) {
                    $locked->status->transitionTo($target::class);
                    $locked->save();
                }
            }

            return $locked->fresh() ?? $locked;
        });
    }
}
