<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutMethodAdministration;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class AdministerPayoutMethod implements PayoutMethodAdministration
{
    public function verify(string $affiliateId, string $methodId): void
    {
        $this->withLockedMethod($affiliateId, $methodId, static function (AffiliatePayoutMethod $method, Affiliate $affiliate): void {
            if (! $method->isVerified()) {
                $method->update(['verified_at' => CarbonImmutable::now()]);
            }
        });
    }

    public function selectDefault(string $affiliateId, string $methodId): void
    {
        $this->withLockedMethod($affiliateId, $methodId, static function (AffiliatePayoutMethod $method, Affiliate $affiliate): void {
            $affiliate->payoutMethods()->where('is_default', true)->whereKeyNot($method->getKey())->update(['is_default' => false]);

            if (! $method->is_default) {
                $method->update(['is_default' => true]);
            }
        });
    }

    /** @param callable(AffiliatePayoutMethod, Affiliate): void $write */
    private function withLockedMethod(string $affiliateId, string $methodId, callable $write): void
    {
        $connection = (new Affiliate)->getConnection();

        if ((new AffiliatePayoutMethod)->getConnection() !== $connection) {
            throw new LogicException('Payout method updates require a shared database connection.');
        }

        $connection->transaction(function () use ($affiliateId, $methodId, $write): void {
            if ((bool) config('affiliates.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(Affiliate::class, $affiliateId);
            }

            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            $method = $affiliate->payoutMethods()->lockForUpdate()->findOrFail($methodId);

            $write($method, $affiliate);
        }, attempts: 3);
    }

    public function setDefault(string $affiliateId, string $type, string $label, string $accountRef): void
    {
        $type = PayoutMethodType::from($type);
        $validated = validator(['label' => $label, 'account_ref' => $accountRef], [
            'label' => ['required', 'string', 'max:255'],
            'account_ref' => ['required', 'string', 'max:255'],
        ])->validate();

        $connection = (new Affiliate)->getConnection();

        if ((new AffiliatePayoutMethod)->getConnection() !== $connection) {
            throw new LogicException('Payout method updates require a shared database connection.');
        }

        $connection->transaction(function () use ($affiliateId, $type, $validated): void {
            if ((bool) config('affiliates.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(Affiliate::class, $affiliateId);
            }

            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            $method = $affiliate->payoutMethods()->where('is_default', true)->orderBy('id')->lockForUpdate()->first();
            $details = ['label' => $validated['label'], 'account_ref' => $validated['account_ref']];

            if ($method === null) {
                $affiliate->payoutMethods()->create([
                    'type' => $type,
                    'details' => $details,
                    'is_default' => true,
                ]);

                return;
            }

            $changed = $method->type !== $type || $method->details !== $details;

            $affiliate->payoutMethods()->where('is_default', true)->whereKeyNot($method->getKey())->update(['is_default' => false]);

            if ($changed) {
                $method->update([
                    'type' => $type,
                    'details' => $details,
                    'verified_at' => null,
                ]);
            }
        }, attempts: 3);
    }
}
