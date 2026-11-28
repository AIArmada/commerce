<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Vouchers\Data\VoucherUsageData;
use AIArmada\Vouchers\Events\VoucherUsageRecorded;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Exceptions\ManualRedemptionNotAllowedException;
use AIArmada\Vouchers\Exceptions\VoucherExpiredException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Exceptions\VoucherUsageLimitException;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\ResolvesVoucherAssignee;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Runtime\VoucherSnapshot;
use AIArmada\Vouchers\Runtime\VoucherUsageRecorder;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Expired;
use Akaunting\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Record usage of a voucher.
 */
final class RecordVoucherUsage implements VoucherUsageRecorder
{
    use AsAction;
    use QueriesVouchers;
    use ResolvesVoucherAssignee;

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
        return (new VoucherModel)->getConnection()->transaction(function () use ($code, $discountAmount, $channel, $metadata, $redeemedBy, $notes, $voucherModel): VoucherUsage {
            $query = $this->voucherQuery()->where('code', $this->normalizeCode($code));

            if ($voucherModel !== null) {
                $query->whereKey($voucherModel->getKey());
            }

            $lockedVoucher = $query->lockForUpdate()->first();

            if (! $lockedVoucher) {
                throw new VoucherNotFoundException("Voucher with code '{$code}' not found.");
            }
            if (config('vouchers.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(VoucherModel::class, $lockedVoucher->getKey(), includeGlobal: (bool) config('vouchers.owner.include_global', false));
            }

            $assignee = $this->resolveAssignee($lockedVoucher);

            if ($assignee !== null
                && (! $redeemedBy instanceof Model
                    || $redeemedBy->getMorphClass() !== $assignee['type']
                    || (string) $redeemedBy->getKey() !== $assignee['id'])
            ) {
                throw InvalidVoucherException::assignedToAnother($lockedVoucher->code);
            }

            $idempotencyKey = $this->resolveIdempotencyKey($metadata);

            if ($idempotencyKey !== null) {
                $existingUsage = VoucherUsage::query()
                    ->where('voucher_id', $lockedVoucher->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingUsage instanceof VoucherUsage) {
                    $sameRedeemer = $existingUsage->redeemed_by_type === $redeemedBy?->getMorphClass()
                        && (string) $existingUsage->redeemed_by_id === (string) $redeemedBy?->getKey();
                    $sameAmount = (string) $existingUsage->discount_amount === (string) $discountAmount->getAmount();
                    $sameCurrency = mb_strtoupper($existingUsage->currency) === mb_strtoupper($discountAmount->getCurrency()->getCurrency());

                    if (! $sameRedeemer || ! $sameAmount || ! $sameCurrency) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'The idempotency key was already used for a different voucher redemption.',
                        ]);
                    }

                    return $existingUsage;
                }
            }
            if ($lockedVoucher->isExpired() || $lockedVoucher->status instanceof Expired) {
                throw VoucherExpiredException::withCode($lockedVoucher->code);
            }

            if (! $lockedVoucher->hasStarted()) {
                throw InvalidVoucherException::notStarted($lockedVoucher->code);
            }

            if ($lockedVoucher->status instanceof Depleted) {
                throw VoucherUsageLimitException::globalLimit($lockedVoucher->code);
            }

            if (! $lockedVoucher->status->canBeUsed()) {
                throw InvalidVoucherException::notActive($lockedVoucher->code);
            }

            if ($channel === config('vouchers.redemption.manual_channel', 'manual')
                && config('vouchers.redemption.manual_requires_flag', true)
                && ! $lockedVoucher->allowsManualRedemption()) {
                throw ManualRedemptionNotAllowedException::forVoucher($lockedVoucher->code);
            }

            $this->assertCurrencyMatches($lockedVoucher, $discountAmount);

            if ((int) $discountAmount->getAmount() < 0) {
                throw ValidationException::withMessages(['discount_amount' => 'Voucher discount cannot be negative.']);
            }
            $currentUses = $lockedVoucher->usage_limit === null
                ? 0
                : VoucherUsage::query()->where('voucher_id', $lockedVoucher->id)->count();

            if ($lockedVoucher->usage_limit !== null) {
                if ($currentUses >= $lockedVoucher->usage_limit) {
                    throw VoucherUsageLimitException::globalLimit($lockedVoucher->code);
                }
            }
            if ($lockedVoucher->usage_limit_per_user === 0 || ($lockedVoucher->usage_limit_per_user !== null && $redeemedBy === null)) {
                throw VoucherUsageLimitException::userLimit($lockedVoucher->code);
            }

            if ($redeemedBy !== null && $lockedVoucher->usage_limit_per_user !== null) {
                $currentUserUses = VoucherUsage::where('voucher_id', $lockedVoucher->id)
                    ->where('redeemed_by_type', $redeemedBy->getMorphClass())
                    ->where('redeemed_by_id', $redeemedBy->getKey())
                    ->count();

                if ($currentUserUses >= $lockedVoucher->usage_limit_per_user) {
                    throw VoucherUsageLimitException::userLimit($lockedVoucher->code);
                }
            }

            $usageAttributes = [
                'voucher_id' => $lockedVoucher->id,
                'idempotency_key' => $idempotencyKey,
                'discount_amount' => $discountAmount->getAmount(),
                'currency' => $discountAmount->getCurrency()->getCurrency(),
                'channel' => $channel ?? 'web',
                'metadata' => $metadata,
                'target_definition' => $lockedVoucher->target_definition,
                'redeemed_by_type' => $redeemedBy?->getMorphClass(),
                'redeemed_by_id' => $redeemedBy === null ? null : (string) $redeemedBy->getKey(),
                'notes' => $notes,
                'used_at' => CarbonImmutable::now(),
            ];

            $usage = $idempotencyKey === null
                ? VoucherUsage::create($usageAttributes)
                : VoucherUsage::query()->createOrFirst(
                    [
                        'voucher_id' => $lockedVoucher->id,
                        'idempotency_key' => $idempotencyKey,
                    ],
                    $usageAttributes,
                );

            if (! $usage->wasRecentlyCreated) {
                return $usage;
            }
            if ($lockedVoucher->usage_limit !== null) {
                $newUsageCount = $currentUses + 1;
                if ($newUsageCount >= $lockedVoucher->usage_limit) {
                    $lockedVoucher->status->transitionTo(Depleted::class);
                    $lockedVoucher->save();
                }
            }

            event(new VoucherUsageRecorded(VoucherSnapshot::fromModel($lockedVoucher), new VoucherUsageData(
                id: $usage->id,
                voucherId: $usage->voucher_id,
                discountAmount: $usage->discount_amount,
                currency: $usage->currency,
                channel: $usage->channel,
                redeemedByType: $usage->redeemed_by_type,
                redeemedById: $usage->redeemed_by_id === null ? null : (string) $usage->redeemed_by_id,
                usedAt: $usage->used_at,
                metadata: $usage->metadata,
                notes: $usage->notes,
            )));

            return $usage;
        });
    }

    private function assertCurrencyMatches(VoucherModel $voucher, Money $discountAmount): void
    {
        $voucherCurrency = $voucher->currency;

        if (! is_string($voucherCurrency) || $voucherCurrency === '') {
            return;
        }

        $usageCurrency = $discountAmount->getCurrency()->getCurrency();

        if (mb_strtoupper($usageCurrency) !== mb_strtoupper($voucherCurrency)) {
            throw ValidationException::withMessages([
                'currency' => "The usage currency ({$usageCurrency}) does not match the voucher currency ({$voucherCurrency}).",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function resolveIdempotencyKey(?array $metadata): ?string
    {
        foreach (['idempotency_key', 'order_id', 'reference'] as $key) {
            $value = data_get($metadata, $key);

            if (is_scalar($value) && mb_trim((string) $value) !== '') {
                return hash('sha256', (string) $value);
            }
        }

        return null;
    }
}
