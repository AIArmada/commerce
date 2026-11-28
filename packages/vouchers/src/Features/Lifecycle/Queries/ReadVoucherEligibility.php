<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Queries;

use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherEligibility;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\QueriesVouchers;
use AIArmada\Vouchers\Features\Lifecycle\Concerns\ResolvesVoucherAssignee;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Runtime\VoucherSnapshot;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Expired;
use AIArmada\Vouchers\States\Paused;

final class ReadVoucherEligibility implements VoucherEligibility
{
    use QueriesVouchers;
    use ResolvesVoucherAssignee;

    public function validate(string $code, mixed $cartCurrency, ?string $actorType, ?string $actorId): VoucherValidationResult
    {
        $code = $this->normalizeCode($code);

        // Find voucher
        $voucher = $this->voucherQuery(withUsageCount: (bool) config('vouchers.validation.check_global_limit', true))
            ->where('code', $code)
            ->first();

        if (! $voucher) {
            return VoucherValidationResult::invalid('Voucher not found.');
        }

        if ($cartCurrency !== null
            && (! is_string($cartCurrency) || mb_strtoupper(mb_trim($cartCurrency)) !== mb_strtoupper($voucher->currency))) {
            return VoucherValidationResult::invalid('Voucher currency does not match the cart currency.');
        }

        // Recipient-bound vouchers refuse every other account. Unmarked
        // vouchers skip this check entirely.
        try {
            $assignee = $this->resolveAssignee($voucher);
        } catch (InvalidVoucherException $exception) {
            return VoucherValidationResult::invalid($exception->getMessage());
        }

        if ($assignee !== null) {
            if ($actorType !== $assignee['type'] || $actorId !== $assignee['id']
            ) {
                return VoucherValidationResult::invalid('This voucher is assigned to another account.');
            }
        }

        // Check start date (before status check, as time-based validations are more specific)
        if (! $voucher->hasStarted()) {
            return VoucherValidationResult::invalid(
                'Voucher is not yet available.',
                ['starts_at' => $voucher->starts_at]
            );
        }

        // Check expiry (before status check, as time-based validations are more specific)
        if ($voucher->isExpired()) {
            return VoucherValidationResult::invalid(
                'Voucher has expired.',
                ['expires_at' => $voucher->expires_at]
            );
        }

        // Check status (after time-based checks)
        if (! $voucher->isActive()) {
            if ($voucher->status instanceof Paused) {
                return VoucherValidationResult::invalid('Voucher is paused.');
            }

            if ($voucher->status instanceof Expired) {
                return VoucherValidationResult::invalid('Voucher has expired.');
            }

            if ($voucher->status instanceof Depleted) {
                return VoucherValidationResult::invalid('Voucher usage limit has been reached.');
            }

            return VoucherValidationResult::invalid('Voucher is not active.');
        }

        // Check global usage limit
        if (config('vouchers.validation.check_global_limit', true)) {
            if (! $voucher->hasUsageLimitRemaining()) {
                return VoucherValidationResult::invalid('Voucher usage limit has been reached.');
            }
        }

        // Check per-user usage limit. Redemption requires an identified customer,
        // so validation must not approve a customer-limited voucher for a guest.
        if ($voucher->usage_limit_per_user !== null) {
            if ($voucher->usage_limit_per_user === 0) {
                return VoucherValidationResult::invalid('Voucher usage limit has been reached.');
            }

            if ($actorType === null || $actorId === null) {
                return VoucherValidationResult::invalid('Sign in to use this voucher.');
            }

            if (config('vouchers.validation.check_user_limit', true)) {
                $usageCount = VoucherUsage::query()
                    ->where('voucher_id', $voucher->id)
                    ->where('redeemed_by_type', $actorType)
                    ->where('redeemed_by_id', $actorId)
                    ->count();

                if ($usageCount >= $voucher->usage_limit_per_user) {
                    return VoucherValidationResult::invalid(
                        'You have already used this voucher the maximum number of times.'
                    );
                }
            }
        }

        return VoucherValidationResult::valid(['voucher' => VoucherSnapshot::fromModel($voucher)->toArray()]);
    }
}
