<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherUpdater;
use AIArmada\Vouchers\Features\Lifecycle\Queries\VoucherSnapshot;
use AIArmada\Vouchers\Features\Lifecycle\Support\VoucherAffiliateOwnershipGuard;
use AIArmada\Vouchers\Features\Lifecycle\Support\VoucherInputValidator;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\States\VoucherStatus;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateVoucher implements VoucherUpdater
{
    use AsAction;
    use NormalizesVoucherCodes;

    public function __construct(private readonly VoucherInputValidator $validator) {}

    /** @param array<string, mixed> $data */
    public function handle(string $voucherId, array $data): VoucherData
    {
        return (new VoucherModel)->getConnection()->transaction(function () use ($voucherId, $data): VoucherData {
            $locked = config('vouchers.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(VoucherModel::class, $voucherId, lockForUpdate: true)
                : VoucherModel::query()->whereKey($voucherId)->lockForUpdate()->firstOrFail();

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

            if (isset($data['code']) && is_string($data['code'])) {
                $data['code'] = $this->normalizeCode($data['code']);
            }

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

            return VoucherSnapshot::fromModel($locked->fresh() ?? $locked);
        });
    }
}
