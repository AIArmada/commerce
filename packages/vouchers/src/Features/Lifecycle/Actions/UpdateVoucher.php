<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Runtime\VoucherUpdater;
use AIArmada\Vouchers\States\VoucherStatus;
use AIArmada\Vouchers\Support\VoucherAffiliateOwnershipGuard;
use AIArmada\Vouchers\Support\VoucherInputValidator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateVoucher implements VoucherUpdater
{
    use AsAction;
    use QueriesVouchers;

    public function __construct(private readonly VoucherInputValidator $validator) {}

    /** @param array<string, mixed> $data */
    public function handle(string | VoucherModel $voucher, array $data): VoucherModel
    {
        $record = $voucher instanceof VoucherModel
            ? $voucher
            : $this->voucherQuery()->where('code', $this->normalizeCode($voucher))->firstOrFail();

        return $record->getConnection()->transaction(function () use ($record, $data): VoucherModel {
            $locked = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(VoucherModel::class, $record->getKey(), lockForUpdate: true)
                : VoucherModel::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

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

            if ($status !== null && ! $status instanceof VoucherStatus && ! is_string($status)) {
                throw ValidationException::withMessages(['status' => 'Invalid voucher status.']);
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
