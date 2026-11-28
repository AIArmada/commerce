<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Fulfillment;

use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Rewards\Contracts\RewardBenefitCouponFulfillment;
use AIArmada\Rewards\Models\RewardBenefitGrant;
use AIArmada\Rewards\Models\RewardBenefitItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Optional aiarmada/vouchers coupon fulfillment adapter for benefit grants.
 */
final class VouchersRewardBenefitCouponFulfillment implements RewardBenefitCouponFulfillment
{
    public function issue(RewardBenefitGrant $grant, RewardBenefitItem $item, CarbonImmutable $decided): string
    {
        $createActionClass = 'AIArmada\\Vouchers\\Features\\Lifecycle\\Api\\VoucherCreator';
        $addToWalletClass = 'AIArmada\\Vouchers\\Features\\Wallet\\Api\\WalletClaim';

        if (! interface_exists($createActionClass) || ! interface_exists($addToWalletClass)) {
            throw new InvalidArgumentException('The vouchers package public API is unavailable.');
        }

        $config = $item->fulfillment_config['voucher'] ?? null;

        if (! is_array($config)) {
            throw new InvalidArgumentException('Coupon fulfillment requires frozen voucher terms on the catalog item.');
        }

        $holder = $this->resolveRecipientModel((string) $grant->recipient_type, (string) $grant->recipient_id);

        if (! $holder instanceof Model) {
            throw new InvalidArgumentException('Coupon recipient account is missing or unsupported.');
        }

        $code = 'RB-' . mb_strtoupper(mb_substr(hash('sha256', (string) $grant->grant_key), 0, 12));

        $voucher = app($createActionClass)->handle([
            'code' => $code,
            'name' => mb_substr((string) $item->title, 0, 120),
            'type' => 'fixed',
            'value' => ((int) $config['value']) * ((int) $grant->quantity),
            'currency' => (string) $config['currency'],
            'usage_limit' => 1,
            'usage_limit_per_user' => 1,
            'starts_at' => $decided,
            'expires_at' => $item->expires_at,
            'description' => 'Reward benefit grant ' . $grant->grant_key,
            'metadata' => [
                'assignee' => [
                    'type' => (string) $grant->recipient_type,
                    'id' => (string) $grant->recipient_id,
                ],
                'grant_key' => (string) $grant->grant_key,
            ],
        ]);

        app($addToWalletClass)->handle($voucher->code, EloquentRecordReference::fromModel($holder), [
            'grant_key' => (string) $grant->grant_key,
            'benefit_key' => (string) $item->benefit_key,
        ], $voucher->id);

        return $voucher->code;
    }

    public function assertCanonicalUsage(RewardBenefitGrant $grant, array $useEvidence): void
    {
        $voucherClass = 'AIArmada\\Vouchers\\Features\\Lifecycle\\Models\\Voucher';
        $usageClass = 'AIArmada\\Vouchers\\Features\\Lifecycle\\Models\\VoucherUsage';

        if (! class_exists($voucherClass) || ! class_exists($usageClass)) {
            throw new InvalidArgumentException('The vouchers package models are unavailable.');
        }

        /** @var Model|null $voucher */
        $voucher = $voucherClass::query()
            ->forOwner(includeGlobal: false)
            ->where('code', (string) $grant->external_ref)
            ->lockForUpdate()
            ->first();

        if (! $voucher instanceof Model) {
            throw new InvalidArgumentException('Issued coupon is missing; refusing consumption.');
        }

        $this->assertGrantIdentity($voucher, $grant);

        $usageId = $useEvidence['voucher_usage_id'] ?? null;

        if (is_string($usageId) && $usageId !== '') {
            /** @var Model|null $usage */
            $usage = $usageClass::query()
                ->whereKey($usageId)
                ->where('voucher_id', $voucher->getKey())
                ->lockForUpdate()
                ->first();

            if (! $usage instanceof Model) {
                throw new InvalidArgumentException('No canonical usage exists for the issued coupon; refusing consumption.');
            }
        } else {
            $matches = $usageClass::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('redeemed_by_type', (string) $grant->recipient_type)
                ->where('redeemed_by_id', (string) $grant->recipient_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($matches->isEmpty()) {
                throw new InvalidArgumentException('No canonical usage exists for the issued coupon; refusing consumption.');
            }

            if ($matches->count() > 1) {
                throw new InvalidArgumentException('Several usages exist for the issued coupon; cite the voucher_usage_id explicitly.');
            }

            /** @var Model $usage */
            $usage = $matches->firstOrFail();
        }

        if ((string) $usage->getAttribute('redeemed_by_type') !== (string) $grant->recipient_type
            || (string) $usage->getAttribute('redeemed_by_id') !== (string) $grant->recipient_id
        ) {
            throw new InvalidArgumentException('The cited usage was not redeemed by the grant recipient; refusing consumption.');
        }
    }

    public function revoke(RewardBenefitGrant $grant): void
    {
        $voucherClass = 'AIArmada\\Vouchers\\Features\\Lifecycle\\Models\\Voucher';
        $usageClass = 'AIArmada\\Vouchers\\Features\\Lifecycle\\Models\\VoucherUsage';
        $expireActionClass = 'AIArmada\\Vouchers\\Features\\Lifecycle\\Api\\VoucherExpiry';

        if (! class_exists($voucherClass) || ! class_exists($usageClass) || ! interface_exists($expireActionClass)) {
            throw new InvalidArgumentException('The vouchers package public API is unavailable.');
        }

        /** @var Model|null $voucher */
        $voucher = $voucherClass::query()
            ->forOwner(includeGlobal: false)
            ->where('code', (string) $grant->external_ref)
            ->lockForUpdate()
            ->first();

        if (! $voucher instanceof Model) {
            throw new InvalidArgumentException('Issued coupon is missing; refusing revocation.');
        }

        $this->assertGrantIdentity($voucher, $grant);

        $used = $usageClass::query()
            ->where('voucher_id', $voucher->getKey())
            ->lockForUpdate()
            ->exists();

        if ($used) {
            throw new InvalidArgumentException('Coupon was used; revocation is closed — open a recovery case instead.');
        }

        OwnerScopeOverride::withoutIncludeGlobal(fn () => app($expireActionClass)->handle((string) $grant->external_ref));
    }

    private function assertGrantIdentity(Model $voucher, RewardBenefitGrant $grant): void
    {
        $metadata = $voucher->getAttribute('metadata');
        $assignee = is_array($metadata) ? ($metadata['assignee'] ?? null) : null;

        if (! is_array($metadata)
            || ($metadata['grant_key'] ?? null) !== $grant->grant_key
            || ! is_array($assignee)
            || ($assignee['type'] ?? null) !== $grant->recipient_type
            || ($assignee['id'] ?? null) !== $grant->recipient_id
            || $voucher->getAttribute('usage_limit') !== 1
            || $voucher->getAttribute('usage_limit_per_user') !== 1) {
            throw new InvalidArgumentException('Issued coupon does not belong to this benefit grant.');
        }
    }

    private function resolveRecipientModel(string $recipientType, string $recipientId): ?Model
    {
        $class = Relation::getMorphedModel($recipientType) ?? $recipientType;

        if ((! is_string($class) || ! class_exists($class)) && $recipientType === 'user') {
            $configured = config('auth.providers.users.model');
            $class = is_string($configured) ? $configured : $class;
        }

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::query()->whereKey($recipientId)->first();
    }
}
