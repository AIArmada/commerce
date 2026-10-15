<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Fulfillment;

use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Models\ReferralReward;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class VouchersReferralFulfillment implements ReferralRewardFulfillment
{
    public function fulfill(ReferralReward $reward): ReferralFulfillmentResult
    {
        $createAction = 'AIArmada\\Vouchers\\Actions\\CreateVoucher';
        $voucherClass = 'AIArmada\\Vouchers\\Models\\Voucher';

        if (! class_exists($createAction) || ! class_exists($voucherClass)) {
            return ReferralFulfillmentResult::failed('The vouchers package public API is unavailable.');
        }

        $code = 'REF' . mb_strtoupper(mb_substr(hash('sha256', $reward->idempotency_key), 0, 24));
        /** @var Model $voucherModel */
        $voucherModel = app($voucherClass);
        $existing = $voucherModel->newQuery()->where('code', $code)->first();
        $marker = 'referrals_reward_idempotency_key';

        if ($existing instanceof Model) {
            $metadata = $existing->getAttribute('metadata');

            if (is_array($metadata)
                && ($metadata[$marker] ?? null) === $reward->idempotency_key
                && $this->isSingleUse($existing)
                && $this->isBoundToBeneficiary($existing, $reward)) {
                return ReferralFulfillmentResult::fulfilled($code);
            }

            return ReferralFulfillmentResult::failed('The deterministic referral voucher code is already used by a voucher that is not a single-use voucher bound to the frozen beneficiary.');
        }

        $spec = $reward->spec;
        $data = is_array($spec['voucher'] ?? null) ? $spec['voucher'] : [];
        $data['code'] = $code;
        $data['currency'] = $reward->currency ?? $data['currency'] ?? config('referrals.defaults.currency', 'MYR');

        // Single use is a rail guarantee: force both alias spellings so a
        // frozen legacy spec can never widen the limit.
        $data['usage_limit'] = 1;
        $data['max_uses'] = 1;
        $data['usage_limit_per_user'] = 1;
        $data['max_uses_per_user'] = 1;

        // The voucher is worth exactly the frozen promise.
        if ($reward->amount_minor !== null) {
            $data['value'] = (int) $reward->amount_minor;
        }

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $metadata[$marker] = $reward->idempotency_key;
        // The beneficiary binding the vouchers package enforces at validation
        // and at usage recording.
        $metadata['assignee'] = [
            'type' => (string) $reward->beneficiary_type,
            'id' => (string) $reward->beneficiary_id,
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
            return ReferralFulfillmentResult::failed('Voucher issuance failed: ' . $exception->getMessage());
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
    private function isBoundToBeneficiary(Model $voucher, ReferralReward $reward): bool
    {
        $metadata = $voucher->getAttribute('metadata');

        if (! is_array($metadata) || ! is_array($metadata['assignee'] ?? null)) {
            return false;
        }

        $assignee = $metadata['assignee'];

        return ($assignee['type'] ?? null) === (string) $reward->beneficiary_type
            && (string) ($assignee['id'] ?? '') === (string) $reward->beneficiary_id;
    }

    public function reverse(ReferralReward $reward, string $reason): ReferralFulfillmentResult
    {
        $expireAction = 'AIArmada\\Vouchers\\Actions\\ExpireVoucher';
        $voucherClass = 'AIArmada\\Vouchers\\Models\\Voucher';
        $code = $reward->external_reference;

        if (! is_string($code) || $code === '' || ! class_exists($expireAction) || ! class_exists($voucherClass)) {
            return ReferralFulfillmentResult::failed('Voucher reversal requires an issued voucher code and the vouchers package.');
        }

        /** @var Model $voucherModel */
        $voucherModel = app($voucherClass);
        $voucher = $voucherModel->newQuery()->where('code', $code)->first();

        if (! $voucher instanceof Model) {
            return ReferralFulfillmentResult::failed('The issued referral voucher could not be found.');
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
            return ReferralFulfillmentResult::failed('Voucher expiry failed: ' . $exception->getMessage());
        }

        return ReferralFulfillmentResult::fulfilled($code);
    }
}
