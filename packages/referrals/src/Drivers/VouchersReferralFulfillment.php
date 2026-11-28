<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Drivers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Data\ReferralRewardContext;
use AIArmada\Vouchers\Data\VoucherData;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

final class VouchersReferralFulfillment implements ReferralRewardFulfillment
{
    public function fulfill(ReferralRewardContext $reward): ReferralFulfillmentResult
    {
        $createAction = 'AIArmada\\Vouchers\\Features\\Lifecycle\\Api\\VoucherCreator';
        $voucherClass = 'AIArmada\\Vouchers\\Features\\Lifecycle\\Models\\Voucher';

        if (! interface_exists($createAction) || ! class_exists($voucherClass)) {
            return ReferralFulfillmentResult::failed('The vouchers package public API is unavailable.');
        }

        /** @var Model $voucherModel */
        $voucherModel = app($voucherClass);
        $connectionFailure = $this->assertSharedConnection($voucherModel, $reward);

        if ($connectionFailure !== null) {
            return $connectionFailure;
        }

        $code = 'REF' . mb_strtoupper(mb_substr(hash('sha256', $reward->idempotencyKey), 0, 24));
        $marker = 'referrals_reward_idempotency_key';

        try {
            $existing = $this->voucherQuery($voucherModel, $reward)->where('code', $code)->first();
        } catch (Throwable $exception) {
            report($exception);

            return ReferralFulfillmentResult::failed('The referral voucher owner context could not be resolved.');
        }

        if ($existing instanceof Model) {
            $metadata = $existing->getAttribute('metadata');

            if (is_array($metadata)
                && ($metadata[$marker] ?? null) === $reward->idempotencyKey
                && $this->isSingleUse($existing)
                && $this->isBoundToBeneficiary($existing, $reward)
                && $this->matchesFrozenValue($existing, $reward)) {
                if (! $this->isRedeemable($existing)) {
                    return ReferralFulfillmentResult::failed('The deterministic referral voucher exists but is no longer redeemable.');
                }

                return ReferralFulfillmentResult::fulfilled($code);
            }

            return ReferralFulfillmentResult::failed('The deterministic referral voucher code is already used by a voucher that is not a single-use voucher bound to the frozen beneficiary.');
        }

        $spec = $reward->spec;
        $data = is_array($spec['voucher'] ?? null) ? $spec['voucher'] : [];
        $data['code'] = $code;
        unset($data['owner_type'], $data['owner_id']);

        $currency = $reward->currency;
        $value = $reward->amountMinor ?? ($spec['voucher']['value'] ?? null);

        if ($currency === null || ! is_string($currency) || $currency === '') {
            return ReferralFulfillmentResult::failed('Voucher rewards require a frozen currency.');
        }

        if (! is_int($value) || $value < 0) {
            return ReferralFulfillmentResult::failed('Voucher rewards require a frozen integer value.');
        }

        $data['currency'] = $currency;
        $data['value'] = $value;
        $data['usage_limit'] = 1;
        $data['usage_limit_per_user'] = 1;

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $metadata[$marker] = $reward->idempotencyKey;
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

            $voucher = config('vouchers.owner.enabled', false)
                ? OwnerContext::withOwner($this->owner($reward), fn () => $action->handle($data))
                : $action->handle($data);

            if (! $voucher instanceof VoucherData) {
                return ReferralFulfillmentResult::failed('The voucher creator did not return voucher data.');
            }
        } catch (Throwable $exception) {
            report($exception);

            return ReferralFulfillmentResult::failed('Voucher issuance failed.');
        }

        return ReferralFulfillmentResult::fulfilled($voucher->code);
    }

    public function reverse(ReferralRewardContext $reward, string $reason): ReferralFulfillmentResult
    {
        $expireAction = 'AIArmada\\Vouchers\\Features\\Lifecycle\\Api\\VoucherExpiry';
        $voucherClass = 'AIArmada\\Vouchers\\Features\\Lifecycle\\Models\\Voucher';
        $code = $reward->externalReference;

        if (! is_string($code) || $code === '' || ! interface_exists($expireAction) || ! class_exists($voucherClass)) {
            return ReferralFulfillmentResult::failed('Voucher reversal requires an issued voucher code and the vouchers package.');
        }

        /** @var Model $voucherModel */
        $voucherModel = app($voucherClass);
        $connectionFailure = $this->assertSharedConnection($voucherModel, $reward);

        if ($connectionFailure !== null) {
            return $connectionFailure;
        }

        try {
            $voucher = $this->voucherQuery($voucherModel, $reward)->where('code', $code)->first();
        } catch (Throwable $exception) {
            report($exception);

            return ReferralFulfillmentResult::failed('The referral voucher owner context could not be resolved.');
        }

        if (! $voucher instanceof Model) {
            return ReferralFulfillmentResult::failed('The issued referral voucher could not be found.');
        }

        $metadata = $voucher->getAttribute('metadata');

        if (! is_array($metadata)
            || ($metadata['referrals_reward_idempotency_key'] ?? null) !== $reward->idempotencyKey
            || ! $this->isSingleUse($voucher)
            || ! $this->isBoundToBeneficiary($voucher, $reward)
            || ! $this->matchesFrozenValue($voucher, $reward)) {
            return ReferralFulfillmentResult::failed('The issued voucher does not belong to this referral reward.');
        }

        if ($this->isExpired($voucher)) {
            return ReferralFulfillmentResult::fulfilled($code);
        }

        try {
            $action = app($expireAction);

            if (! method_exists($action, 'handle')) {
                return ReferralFulfillmentResult::failed('The vouchers expire action does not expose its public handle method.');
            }

            $owner = $this->owner($reward);

            OwnerContext::withOwner($owner, fn () => OwnerScopeOverride::withoutIncludeGlobal(
                fn () => $action->handle($code),
            ));
        } catch (Throwable $exception) {
            report($exception);

            return ReferralFulfillmentResult::failed('Voucher expiry failed.');
        }

        $refreshed = $voucher->fresh();

        if (! $refreshed instanceof Model || ! $this->isExpired($refreshed)) {
            return ReferralFulfillmentResult::failed('Voucher expiry did not expire the referral voucher.');
        }

        return ReferralFulfillmentResult::fulfilled($code);
    }

    /**
     * @return Builder<Model>
     */
    private function voucherQuery(Model $voucherModel, ReferralRewardContext $reward): Builder
    {
        $query = $voucherModel->newQuery();

        if (! (bool) config('vouchers.owner.enabled', false)) {
            return $query;
        }

        return OwnerQuery::applyToEloquentBuilder(
            $query->withoutGlobalScope(OwnerScope::class),
            $this->owner($reward),
            false,
        );
    }

    private function owner(ReferralRewardContext $reward): ?Model
    {
        if (($reward->ownerType === null) !== ($reward->ownerId === null)) {
            throw new RuntimeException('The referral reward owner tuple is incomplete.');
        }

        return OwnerContext::fromTypeAndId($reward->ownerType, $reward->ownerId);
    }

    private function assertSharedConnection(Model $voucherModel, ReferralRewardContext $reward): ?ReferralFulfillmentResult
    {
        if ($reward->connectionName === '' || $voucherModel->getConnection()->getName() !== $reward->connectionName) {
            return ReferralFulfillmentResult::failed('Voucher referral fulfilment requires a shared database connection.');
        }

        return null;
    }

    private function isRedeemable(Model $voucher): bool
    {
        if (! method_exists($voucher, 'canBeRedeemed')) {
            return false;
        }

        return (bool) $voucher->canBeRedeemed();
    }

    private function isExpired(Model $voucher): bool
    {
        $status = $voucher->getAttribute('status');

        return is_object($status) && str_ends_with($status::class, '\\Expired');
    }

    /**
     * A referral voucher is redeemable once for the member and once overall.
     */
    private function isSingleUse(Model $voucher): bool
    {
        return $voucher->getAttribute('usage_limit') === 1
            && $voucher->getAttribute('usage_limit_per_user') === 1;
    }

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
        $type = $type instanceof BackedEnum ? $type->value : $type;

        return is_int($expectedValue)
            && (int) $voucher->getAttribute('value') === $expectedValue
            && $type === ($spec['type'] ?? null)
            && $voucher->getAttribute('currency') === $reward->currency;
    }
}
