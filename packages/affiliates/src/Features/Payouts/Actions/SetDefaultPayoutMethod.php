<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutMethodAdministration;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use LogicException;

final readonly class SetDefaultPayoutMethod implements PayoutMethodAdministration
{
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
