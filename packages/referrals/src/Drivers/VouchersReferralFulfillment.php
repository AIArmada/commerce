<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Drivers;

use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Data\ReferralRewardContext;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class VouchersReferralFulfillment implements ReferralRewardFulfillment
{
    public function fulfill(ReferralRewardContext $reward): ReferralFulfillmentResult
    {
        $createAction = 'AIArmada\\Vouchers\\Actions\\CreateVoucher';
        $voucherClass = 'AIArmada\\Vouchers\\Models\\Voucher';

        if (! class_exists($createAction) || ! class_exists($voucherClass)) {
            return ReferralFulfillmentResult::failed('The vouchers package public API is unavailable.');
        }

        $code = 'REF' . mb_strtoupper(mb_substr(hash('sha256', $reward->idempotencyKey), 0, 24));
        /** @var Model $voucherModel */
        $voucherModel = app($voucherClass);
        $existing = $voucherModel->newQuery()->where('code', $code)->first();
        $marker = 'referrals_reward_idempotency_key';

        if ($existing instanceof Model) {
            $metadata = $existing->getAttribute('metadata');

            if (is_array($metadata)
                && ($metadata[$marker] ?? null) === $reward->idempotencyKey
                && $this->isSingleUse($existing)
                && $this->isBoundToBeneficiary($existing, $reward)
                && $this->matchesFrozenValue($existing, $reward)) {
                return ReferralFulfillmentResult::fulfilled($code);
            }

            return ReferralFulfillmentResult::failed('The deterministic referral voucher code is already used by a voucher that is not a single-use voucher bound to the frozen beneficiary.');
        }

        $spec = $reward->spec;
        $data = is_array($spec['voucher'] ?? null) ? $spec['voucher'] : [];
        $data['code'] = $code;
        unset($data['owner_type'], $data['owner_id']);
        $data['currency'] = $reward->currency ?? $data['currency'] ?? config('referrals.defaults.currency', 'MYR');

        // Explicit limits take precedence over submitted voucher settings.
        $data['usage_limit'] = 1;
        unset($data['max_uses']);
        $data['usage_limit_per_user'] = 1;
        unset($data['max_uses_per_user']);

        // The voucher is worth exactly the frozen promise.
        if ($reward->amountMinor !== null) {
            $data['value'] = (int) $reward->amountMinor;
        }

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $metadata[$marker] = $reward->idempotencyKey;
        // The beneficiary binding the vouchers package enforces at validation
        // and at usage recording.
        $metadata['assignee'] = [
            'type' => (string) $reward->beneficiaryType,
            'id' => (string) $reward->beneficiaryId,
        ];
        $data['metadata'] = $metadata;

        try {
            $action = app($createAction);

            if (! method_exists($action, 'handle')) {
                return ReferralFulfillmentResult::failed('The vouchers create action does not expose its public handle method.');
            }

            $voucher = $action->handle($data);

            if (! $voucher instanceof Model) {
                return ReferralFulfillmentResult::failed('The vouchers create action did not return a voucher model.');
            }
        } catch (Throwable $exception) {
            report($exception);

            return ReferralFulfillmentResult::failed('Voucher issuance failed.');
        }

        return ReferralFulfillmentResult::fulfilled((string) $voucher->getAttribute('code'));
    }

    /**
     * A referral voucher is redeemable once for the member and once overall.
     * A pre-fix unlimited or partially limited voucher must never be
     * re-presented as the fulfilment of a referral promise.
     */
    private function isSingleUse(Model $voucher): bool
    {
        return $voucher->getAttribute('usage_limit') === 1
            && $voucher->getAttribute('usage_limit_per_user') === 1;
    }

    /**
     * The voucher's assignee marker names exactly the frozen beneficiary.
     */
    private function isBoundToBeneficiary(Model $voucher, ReferralRewardContext $reward): bool
    {
        $metadata = $voucher->getAttribute('metadata');

        if (! is_array($metadata) || ! is_array($metadata['assignee'] ?? null)) {
            return false;
        }

        $assignee = $metadata['assignee'];

        return ($assignee['type'] ?? null) === (string) $reward->beneficiaryType
            && (string) ($assignee['id'] ?? '') === (string) $reward->beneficiaryId;
    }

    private function matchesFrozenValue(Model $voucher, ReferralRewardContext $reward): bool
    {
        $spec = $reward->spec['voucher'] ?? [];
        $expectedValue = $reward->amountMinor ?? ($spec['value'] ?? null);
        $type = $voucher->getAttribute('type');
        $type = $type instanceof \BackedEnum ? $type->value : $type;

        return is_int($expectedValue)
            && (int) $voucher->getAttribute('value') === $expectedValue
            && $type === ($spec['type'] ?? null)
            && $voucher->getAttribute('currency') === $reward->currency;
    }

    public function reverse(ReferralRewardContext $reward, string $reason): ReferralFulfillmentResult
    {
        $expireAction = 'AIArmada\\Vouchers\\Actions\\ExpireVoucher';
        $voucherClass = 'AIArmada\\Vouchers\\Models\\Voucher';
        $code = $reward->externalReference;

        if (! is_string($code) || $code === '' || ! class_exists($expireAction) || ! class_exists($voucherClass)) {
            return ReferralFulfillmentResult::failed('Voucher reversal requires an issued voucher code and the vouchers package.');
        }

        /** @var Model $voucherModel */
        $voucherModel = app($voucherClass);
        $voucher = $voucherModel->newQuery()->where('code', $code)->first();

        if (! $voucher instanceof Model) {
            return ReferralFulfillmentResult::failed('The issued referral voucher could not be found.');
        }

        $metadata = $voucher->getAttribute('metadata');

        if (! is_array($metadata)
            || ($metadata['referrals_reward_idempotency_key'] ?? null) !== $reward->idempotencyKey
            || ! $this->isBoundToBeneficiary($voucher, $reward)) {
            return ReferralFulfillmentResult::failed('The issued voucher does not belong to this referral reward.');
        }

        $status = $voucher->getAttribute('status');

        if (is_object($status) && str_ends_with($status::class, '\\Expired')) {
            return ReferralFulfillmentResult::fulfilled($code);
        }

        try {
            $action = app($expireAction);

            if (! method_exists($action, 'handle')) {
                return ReferralFulfillmentResult::failed('The vouchers expire action does not expose its public handle method.');
            }

            $action->handle($code);
        } catch (Throwable $exception) {
            report($exception);

            return ReferralFulfillmentResult::failed('Voucher expiry failed.');
        }

        return ReferralFulfillmentResult::fulfilled($code);
    }
}
