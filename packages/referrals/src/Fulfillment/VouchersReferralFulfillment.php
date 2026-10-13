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

            if (is_array($metadata) && ($metadata[$marker] ?? null) === $reward->idempotency_key) {
                return ReferralFulfillmentResult::fulfilled($code);
            }

            return ReferralFulfillmentResult::failed('The deterministic referral voucher code is already used by another voucher.');
        }

        $spec = $reward->spec;
        $data = is_array($spec['voucher'] ?? null) ? $spec['voucher'] : [];
        $data['code'] = $code;
        $data['currency'] = $reward->currency ?? $data['currency'] ?? config('referrals.defaults.currency', 'MYR');
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $metadata[$marker] = $reward->idempotency_key;
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
